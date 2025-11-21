<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseOrderItem;
use App\Models\Stock;
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

            // supplier per-PO (bukan per item)
            $supplierId = $po->supplier_id;

            // Kunci PO agar perhitungan sisa tidak race
            DB::table('purchase_orders')->where('id', $poId)->lockForUpdate()->value('id');

            // Hitung sisa berbasis RECEIPT yang SUDAH POSTED
            $ids = $receipt->items->pluck('purchase_order_item_id')->all();

            $ordered = $po->items->pluck('ordered_quantity', 'id');

            $posted = DB::table('purchase_receipt_items as pri')
                ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                ->where('pr.status', 'posted')
                ->whereIn('pri.purchase_order_item_id', $ids)
                ->groupBy('pri.purchase_order_item_id')
                ->selectRaw('pri.purchase_order_item_id, SUM(pri.received_quantity) AS received_total')
                ->pluck('received_total', 'purchase_order_item_id');

            $violations = [];
            foreach ($receipt->items as $it) {
                if ((float)$it->received_quantity <= 0) {
                    continue;
                }
                $itemId    = (int) $it->purchase_order_item_id;
                $remaining = max(0, (float)($ordered[$itemId] ?? 0) - (float)($posted[$itemId] ?? 0));

                if ((float)$it->received_quantity > $remaining) {
                    $violations[] = "{$it->material_name}: qty {$it->received_quantity} > sisa {$remaining}";
                }
            }

            if ($violations) {
                return back()->with('warning', 'Tidak bisa posting (melebihi sisa): ' . implode(' | ', $violations));
            }

            // 1) Tambah stok per item (per-PO) + movement IN pada tanggal penerimaan
            foreach ($receipt->items as $it) {
                if ((float)$it->received_quantity <= 0) {
                    continue;
                }

                // Ambil material_code dari PO Item yang terkait
                $poi = $po->items->firstWhere('id', $it->purchase_order_item_id);

                $materialCode = $poi && $poi->material_code !== null
                    ? strtoupper(trim((string) $poi->material_code))
                    : null;

                $materialName = $it->material_name;
                $unit         = $it->unit;

                /**
                 * Baris stok per-PO:
                 *   1 baris = 1 supplier + 1 PO + 1 nama material + 1 unit
                 *
                 * Di DB VPS kamu sudah ada unique index
                 *   stocks_unique_sup_mat_unit_po_v2
                 * yang kemungkinan besar isinya:
                 *   (supplier_id, material_name, unit, purchase_order_id)
                 *
                 * Jadi di sini kita cari stok EXISTING berdasarkan kombinasi itu,
                 * lalu kalau tidak ada baru buat baris baru.
                 */

                $stock = Stock::where('purchase_order_id', $poId)
                    ->where('supplier_id', $supplierId)
                    ->where('material_name', $materialName)
                    ->where('unit', $unit)
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {
                    // Belum ada baris stok untuk kombinasi ini → buat baru
                    $stock = new Stock();
                    $stock->purchase_order_id = $poId;
                    $stock->supplier_id       = $supplierId;
                    $stock->material_name     = $materialName;
                    $stock->unit              = $unit;
                    $stock->quantity          = 0;
                }

                // Selalu update kode & info PO terakhir
                $stock->material_code   = $materialCode;
                $stock->last_po_id      = $poId;
                $stock->last_po_number  = $poNumber;

                // Tambah qty ke stok batch ini
                $stock->quantity = (float) $stock->quantity + (float) $it->received_quantity;
                $stock->save();

                // Movement IN pada tanggal penerimaan (bukan now)
                StockMovement::create([
                    'stock_id'      => $stock->id,
                    'supplier_id'   => $stock->supplier_id,
                    'material_name' => $stock->material_name,
                    'unit'          => $stock->unit,
                    'direction'     => StockMovement::DIR_IN,
                    'quantity'      => (float) $it->received_quantity,
                    'notes'         => $it->notes,
                    'po_number'     => $poNumber,
                    'moved_at'      => $receipt->receipt_date->startOfDay(),
                ]);
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

            // 4) Tandai PO complete bila seluruh item terpenuhi (berdasarkan POSTED)
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
