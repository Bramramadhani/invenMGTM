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
                // Lewati baris nol
                if ((float)$it->received_quantity <= 0) {
                    continue;
                }
                $itemId = (int) $it->purchase_order_item_id;
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

                // Baris stok per-PO
                $stock = Stock::where([
                        'supplier_id'   => $it->supplier_id,
                        'material_name' => $it->material_name,
                        'unit'          => $it->unit,
                        'last_po_id'    => $poId,
                    ])
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {
                    $stock = Stock::create([
                        'supplier_id'    => $it->supplier_id,
                        'material_name'  => $it->material_name,
                        'material_code'  => $materialCode,
                        'unit'           => $it->unit,
                        'last_po_id'     => $poId,
                        'last_po_number' => $poNumber,
                        'quantity'       => 0,
                    ]);
                } else {
                    if ($materialCode && $stock->material_code !== $materialCode) {
                        $stock->material_code = $materialCode;
                    }
                }

                // Tambahkan qty & sinkron info PO
                $stock->quantity       = (float) $stock->quantity + (float) $it->received_quantity;
                $stock->last_po_id     = $poId;
                $stock->last_po_number = $poNumber;
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
                    'ref_type'      => 'purchase_receipts',
                    'ref_id'        => $receipt->id,
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
