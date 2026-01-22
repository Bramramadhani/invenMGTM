<?php

/**
 * RACE CONDITION FIX (Dec 2025):
 *
 * - Proses posting receipt + update stok dilakukan di dalam satu DB::transaction
 *   dengan lock pada Purchase Order terkait.
 * - Ini mencegah 2 proses concurrent mengupdate stok PO yang sama secara tidak konsisten.
 *
 * Catatan: Validasi "qty <= sisa" dihapus agar over-receive diperbolehkan.
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseOrderItem;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseReceiptPostingController extends Controller
{
    public function post(PurchaseReceipt $receipt)
    {
        return DB::transaction(function () use ($receipt) {

            $receipt = PurchaseReceipt::with(['items', 'purchaseOrder.items'])
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($receipt->status !== 'draft') {
                return back()->with('warning', 'Dokumen sudah diposting/void.');
            }

            if ($receipt->items->isEmpty()) {
                return back()->with('warning', 'Tidak bisa posting: tidak ada item pada receipt ini.');
            }

            if (empty($receipt->receipt_date)) {
                return back()->with('warning', 'Tanggal penerimaan (receipt_date) wajib diisi sebelum posting.');
            }

            $po       = $receipt->purchaseOrder;
            $poId     = $po?->id;
            $poNumber = $po?->po_number;

            if (!$poId) {
                return back()->with('warning', 'Receipt harus terkait PO agar stok dipisah per-PO.');
            }

            if (method_exists($po, 'isFullFob') && $po->isFullFob()) {
                return back()->with('warning', 'PO FULL FOB tidak menggunakan penerimaan/receipt.');
            }

            // supplier per-PO (bukan per item)
            $supplierId = $po->supplier_id;

            // Kunci PO agar update stok & agregat konsisten
            DB::table('purchase_orders')->where('id', $poId)->lockForUpdate()->value('id');

            // Catatan: di sini TIDAK ada lagi validasi "melebihi sisa".
            // Over-receive diperbolehkan; stok dan actual_arrived_quantity
            // tetap dihitung berdasarkan semua receipt POSTED.

            // Total yang sudah POSTED per item (sebelum receipt ini)
            $ids = $receipt->items->pluck('purchase_order_item_id')->all();
            $ordered = $po->items->pluck('ordered_quantity', 'id');

            $posted = DB::table('purchase_receipt_items as pri')
                ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                ->where('pr.status', 'posted')
                ->whereIn('pri.purchase_order_item_id', $ids)
                ->groupBy('pri.purchase_order_item_id')
                ->selectRaw('pri.purchase_order_item_id, SUM(pri.received_quantity) AS received_total')
                ->pluck('received_total', 'purchase_order_item_id');

            // 1) Tambah stok per item (per-PO) + movement IN pada tanggal penerimaan
            foreach ($receipt->items as $it) {
                $receivedQty = (float) $it->received_quantity;
                if ($receivedQty <= 0) {
                    continue;
                }

                // Ambil material_code dari PO Item yang terkait
                $poi = $po->items->firstWhere('id', $it->purchase_order_item_id);

                $materialCode = $poi && $poi->material_code !== null
                    ? strtoupper(trim((string) $poi->material_code))
                    : null;

                $materialName = $it->material_name;
                $unit         = $it->unit;

                $itemId    = (int) $it->purchase_order_item_id;
                $remaining = max(0, (float) ($ordered[$itemId] ?? 0) - (float) ($posted[$itemId] ?? 0));
                $qtyToPo   = min($receivedQty, $remaining);
                $qtyToGlob = $receivedQty - $qtyToPo;

                /**
                 * Baris stok per-PO:
                 *   1 baris = 1 supplier + 1 PO + 1 nama material + 1 unit
                 *
                 * Di DB VPS kamu sudah ada unique index
                 *   stocks_unique_sup_mat_unit_po_v2
                 *   (kemungkinan: supplier_id, material_name, unit, purchase_order_id)
                 */

                if ($qtyToPo > 0) {
                    $stock = Stock::where('purchase_order_id', $poId)
                        ->where('supplier_id', $supplierId)
                        ->where('material_name', $materialName)
                        ->where('unit', $unit)
                        ->lockForUpdate()
                        ->first();
                    if (!$stock) {
                        // Belum ada baris stok untuk kombinasi ini -> buat baru
                        $stock = new Stock();
                        $stock->purchase_order_id = $poId;
                        $stock->supplier_id       = $supplierId;
                        $stock->material_name     = $materialName;
                        $stock->unit              = $unit;
                        $stock->quantity          = 0;
                    }
                    $stock->material_code   = $materialCode;
                    $stock->last_po_id      = $poId;
                    $stock->last_po_number  = $poNumber;
                    $oldQty          = (float) $stock->quantity;
                    $stock->quantity = $oldQty + $qtyToPo;
                    $stock->save();
                    // History: penerimaan barang dari PO (pembelian)
                    StockHistory::recordChange(
                        $stock,
                        $oldQty,
                        (float) $stock->quantity,
                        StockHistory::TYPE_PO_RECEIVE,
                        'Posting receipt ' . ($receipt->receipt_number ?? $receipt->id),
                        Auth::id()
                    );
                    // Movement IN pada tanggal penerimaan (bukan now)
                    StockMovement::create([
                        'stock_id'      => $stock->id,
                        'supplier_id'   => $stock->supplier_id,
                        'material_name' => $stock->material_name,
                        'unit'          => $stock->unit,
                        'direction'     => StockMovement::DIR_IN,
                        'quantity'      => $qtyToPo,
                        'notes'         => $it->notes,
                        'po_number'     => $poNumber,
                        'moved_at'      => $receipt->receipt_date->startOfDay(),
                    ]);
                }

                if ($qtyToGlob > 0) {
                    $globalStock = Stock::whereNull('purchase_order_id')
                        ->whereNull('buyer_id')
                        ->where('supplier_id', $supplierId)
                        ->where('material_name', $materialName)
                        ->where('unit', $unit)
                        ->lockForUpdate()
                        ->first();

                    if (!$globalStock) {
                        $globalStock = new Stock();
                        $globalStock->purchase_order_id = null;
                        $globalStock->supplier_id       = $supplierId;
                        $globalStock->material_name     = $materialName;
                        $globalStock->unit              = $unit;
                        $globalStock->quantity          = 0;
                    }

                    $globalStock->material_code  = $materialCode;
                    $globalStock->last_po_id     = $poId;
                    $globalStock->last_po_number = $poNumber;

                    $oldGlob = (float) $globalStock->quantity;
                    $globalStock->quantity = $oldGlob + $qtyToGlob;
                    $globalStock->save();

                    StockHistory::recordChange(
                        $globalStock,
                        $oldGlob,
                        (float) $globalStock->quantity,
                        StockHistory::TYPE_PO_RECEIVE,
                        'Over-receive ke stok global ' . ($receipt->receipt_number ?? $receipt->id),
                        Auth::id()
                    );

                    StockMovement::create([
                        'stock_id'      => $globalStock->id,
                        'supplier_id'   => $globalStock->supplier_id,
                        'material_name' => $globalStock->material_name,
                        'unit'          => $globalStock->unit,
                        'direction'     => StockMovement::DIR_IN,
                        'quantity'      => $qtyToGlob,
                        'notes'         => $it->notes ? 'Global: ' . $it->notes : 'Global stock over-receive',
                        'po_number'     => $poNumber,
                        'moved_at'      => $receipt->receipt_date->startOfDay(),
                    ]);
                }

                $posted[$itemId] = (float) ($posted[$itemId] ?? 0) + $qtyToPo;
            }

            // 2) Rekap actual_arrived_quantity dari POSTED saja
            $grouped = $receipt->items->groupBy('purchase_order_item_id');
            foreach ($grouped as $poiId => $rows) {
                $totalPosted = (float) DB::table('purchase_receipt_items as pri')
                    ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                    ->where('pr.status', 'posted')
                    ->where('pri.purchase_order_item_id', $poiId)
                    ->sum('pri.received_quantity');

                PurchaseOrderItem::whereKey($poiId)->update([
                    'actual_arrived_quantity' => $totalPosted,
                ]);
            }

            // 3) Update status receipt -> posted
            $receipt->update([
                'status'    => 'posted',
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            // 4) Tandai PO complete bila total RECEIVED >= ORDERED
            $po = $receipt->purchaseOrder->fresh('items');

            $postedPerItem = DB::table('purchase_receipt_items as pri')
                ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                ->where('pr.status', 'posted')
                ->whereIn('pri.purchase_order_item_id', $po->items->pluck('id'))
                ->groupBy('pri.purchase_order_item_id')
                ->selectRaw('pri.purchase_order_item_id, SUM(pri.received_quantity) AS received_total')
                ->pluck('received_total', 'purchase_order_item_id');

            $allFulfilled = $po->items->every(function ($item) use ($postedPerItem) {
                $ordered  = (float) $item->ordered_quantity;
                $received = (float) ($postedPerItem[$item->id] ?? 0);
                return $received >= $ordered;
            });

            if ($allFulfilled) {
                $po->update(['is_completed' => true]);
            }

            return back()->with('success', 'Receipt berhasil diposting');
        });
    }
}