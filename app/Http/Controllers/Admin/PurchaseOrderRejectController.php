<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReject;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderRejectController extends Controller
{
    /**
     * Menyimpan data reject untuk beberapa item sekaligus dari form global.
     */
    public function store(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (method_exists($purchaseOrder, 'isFullFob') && $purchaseOrder->isFullFob()) {
            return back()->with('warning', 'PO FULL FOB tidak menggunakan fitur reject dari penerimaan.');
        }

        Log::info('=== MULAI PROSES REJECT MULTI ITEM ===', [
            'po_id' => $purchaseOrder->id,
            'data' => $request->all(),
        ]);

        $rejects = $request->input('rejects', []);

        if (empty($rejects)) {
            return back()->with('warning', 'Tidak ada data reject yang diisi.');
        }

        try {
            DB::transaction(function () use ($rejects, $purchaseOrder) {
                foreach ($rejects as $itemId => $data) {
                    $qty = (float) ($data['quantity'] ?? 0);
                    $notes = trim($data['notes'] ?? '');
                    if ($qty <= 0) {
                        continue; // skip jika tidak ada qty reject
                    }

                    /** @var PurchaseOrderItem|null $item */
                    $item = PurchaseOrderItem::find($itemId);
                    if (!$item) {
                        Log::warning("Item PO ID {$itemId} tidak ditemukan");
                        continue;
                    }

                    // Cari stok berdasarkan PO dan material
                    $stock = null;

                    if (!empty($item->material_code)) {
                        $stock = Stock::where('purchase_order_id', $purchaseOrder->id)
                            ->whereRaw('UPPER(material_code) = ?', [strtoupper(trim($item->material_code))])
                            ->lockForUpdate()
                            ->first();
                    }

                    if (!$stock) {
                        $stock = Stock::where('purchase_order_id', $purchaseOrder->id)
                            ->whereRaw('LOWER(material_name) = ?', [strtolower(trim($item->material_name))])
                            ->whereRaw('LOWER(unit) = ?', [strtolower(trim($item->unit))])
                            ->lockForUpdate()
                            ->first();
                    }

                    if (!$stock) {
                        $stock = Stock::create([
                            'purchase_order_id' => $purchaseOrder->id,
                            'supplier_id'       => $purchaseOrder->supplier_id ?? null,
                            'material_name'     => $item->material_name,
                            'material_code'     => $item->material_code,
                            'unit'             => $item->unit,
                            'quantity'         => 0,
                            'last_po_id'       => $purchaseOrder->id,
                            'last_po_number'   => $purchaseOrder->po_number,
                        ]);
                        Log::warning('Stok tidak ditemukan, membuat stok baru', ['stock_id' => $stock->id]);
                    }

                    // Pastikan stok cukup
                    if ((float)$stock->quantity < $qty) {
                        throw new \Exception("Stok tidak cukup untuk reject {$item->material_name} (tersedia {$stock->quantity})");
                    }

                    // Kurangi stok
                    $stock->decrement('quantity', $qty);
                    $stock->update([
                        'last_po_id'     => $purchaseOrder->id,
                        'last_po_number' => $purchaseOrder->po_number,
                    ]);

                    // Simpan data reject
                    PurchaseOrderReject::create([
                        'purchase_order_id'       => $purchaseOrder->id,
                        'purchase_order_item_id'  => $item->id,
                        'stock_id'                => $stock->id,
                        'supplier_id'             => $purchaseOrder->supplier_id ?? null,
                        'material_name'           => $item->material_name,
                        'unit'                    => $item->unit,
                        'reject_quantity'         => $qty,
                        'new_notes'               => $notes,
                        'reason'                  => 'Reject otomatis dari modal global',
                        'rejected_at'             => now(),
                        'created_by'              => Auth::id(),
                    ]);

                    Log::info("Reject item {$item->material_name} berhasil disimpan", [
                        'qty' => $qty,
                        'notes' => $notes,
                        'stock_id' => $stock->id
                    ]);
                }
            });

            Log::info('=== PROSES REJECT MULTI ITEM SELESAI TANPA ERROR ===');
            return back()->with('success', 'Data reject berhasil disimpan dan stok diperbarui.');
        } catch (\Throwable $e) {
            Log::error('ERROR saat reject multi item', [
                'po_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('warning', 'Gagal mencatat reject: ' . $e->getMessage());
        }
    }
}
