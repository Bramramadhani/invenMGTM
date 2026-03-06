<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderStyle;
use App\Models\PurchaseReceipt;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    private function normalizeCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        return $code !== '' ? $code : null;
    }

    private function lockStockRow(
        int $supplierId,
        ?int $purchaseOrderId,
        string $materialName,
        ?string $materialCode,
        string $unit
    ): ?Stock {
        $query = Stock::query()
            ->where('supplier_id', $supplierId)
            ->where('material_name', $materialName)
            ->where('unit', $unit);

        if ($purchaseOrderId === null) {
            $query->whereNull('purchase_order_id')
                ->whereNull('buyer_id');
        } else {
            $query->where('purchase_order_id', $purchaseOrderId);
        }

        if ($materialCode) {
            $query->whereRaw('UPPER(TRIM(material_code)) = ?', [$materialCode]);
        } else {
            $query->whereNull('material_code');
        }

        return $query->lockForUpdate()->first();
    }

    private function applyStockDelta(Stock $stock, float $delta, string $poNumber, string $reason): void
    {
        if (abs($delta) < 0.0000001) {
            return;
        }

        $oldQty = (float) $stock->quantity;
        $newQty = $oldQty + $delta;

        if ($newQty < -0.0000001) {
            throw ValidationException::withMessages([
                'items' => "Rebalancing stok membuat qty negatif untuk '{$stock->material_name}'.",
            ]);
        }

        $stock->quantity = $newQty;
        $stock->save();

        StockHistory::recordChange(
            $stock,
            $oldQty,
            $newQty,
            StockHistory::TYPE_MANUAL_CORRECTION,
            $reason,
            auth()->id()
        );

        StockMovement::create([
            'stock_id'      => $stock->id,
            'supplier_id'   => $stock->supplier_id,
            'material_name' => $stock->material_name,
            'unit'          => $stock->unit,
            'direction'     => $delta > 0 ? StockMovement::DIR_IN : StockMovement::DIR_OUT,
            'quantity'      => abs($delta),
            'notes'         => $reason,
            'po_number'     => $poNumber,
            'moved_at'      => now(),
        ]);
    }

    private function rebalancePostedItemStocksOnOrderedQtyChange(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $item,
        float $oldOrderedQty,
        float $newOrderedQty,
        float $postedQty
    ): void {
        $oldAllocatedToPo = min($oldOrderedQty, $postedQty);
        $newAllocatedToPo = min($newOrderedQty, $postedQty);
        $deltaAllocatedToPo = $newAllocatedToPo - $oldAllocatedToPo;

        if (abs($deltaAllocatedToPo) < 0.0000001) {
            return;
        }

        $supplierId   = (int) $purchaseOrder->supplier_id;
        $poId         = (int) $purchaseOrder->id;
        $poNumber     = (string) $purchaseOrder->po_number;
        $materialName = trim((string) $item->material_name);
        $materialCode = $this->normalizeCode($item->material_code);
        $unit         = trim((string) $item->unit);

        $poStock = $this->lockStockRow(
            $supplierId,
            $poId,
            $materialName,
            $materialCode,
            $unit
        );

        if (!$poStock) {
            $poStock = new Stock();
            $poStock->purchase_order_id = $poId;
            $poStock->supplier_id       = $supplierId;
            $poStock->material_name     = $materialName;
            $poStock->material_code     = $materialCode;
            $poStock->unit              = $unit;
            $poStock->quantity          = 0.0;
            $poStock->last_po_id        = $poId;
            $poStock->last_po_number    = $poNumber;
            $poStock->save();
        }

        $globalStock = $this->lockStockRow(
            $supplierId,
            null,
            $materialName,
            $materialCode,
            $unit
        );

        if (!$globalStock) {
            $globalStock = new Stock();
            $globalStock->purchase_order_id = null;
            $globalStock->supplier_id       = $supplierId;
            $globalStock->buyer_id          = null;
            $globalStock->material_name     = $materialName;
            $globalStock->material_code     = $materialCode;
            $globalStock->unit              = $unit;
            $globalStock->quantity          = 0.0;
            $globalStock->last_po_id        = $poId;
            $globalStock->last_po_number    = $poNumber;
            $globalStock->save();
        }

        $reason = 'Rebalancing alokasi stok PO ' . $poNumber
            . ' untuk ' . $materialName
            . ': ordered ' . $oldOrderedQty . ' -> ' . $newOrderedQty;

        if ($deltaAllocatedToPo > 0) {
            $availableGlobal = (float) $globalStock->quantity;
            if ($availableGlobal + 0.0000001 < $deltaAllocatedToPo) {
                throw ValidationException::withMessages([
                    'items' => "Stok global '{$materialName}' tidak cukup untuk dipindah ke PO. "
                        . "Butuh {$deltaAllocatedToPo}, tersedia {$availableGlobal}.",
                ]);
            }

            $poStock->last_po_id     = $poId;
            $poStock->last_po_number = $poNumber;
            $globalStock->last_po_id     = $poId;
            $globalStock->last_po_number = $poNumber;

            $this->applyStockDelta($poStock, $deltaAllocatedToPo, $poNumber, $reason);
            $this->applyStockDelta($globalStock, -$deltaAllocatedToPo, $poNumber, $reason);

            return;
        }

        $shiftToGlobal = abs($deltaAllocatedToPo);
        $availablePo = (float) $poStock->quantity;
        if ($availablePo + 0.0000001 < $shiftToGlobal) {
            throw ValidationException::withMessages([
                'items' => "Stok PO '{$materialName}' tidak cukup untuk dipindah ke global. "
                    . "Butuh {$shiftToGlobal}, tersedia {$availablePo}.",
            ]);
        }

        $poStock->last_po_id     = $poId;
        $poStock->last_po_number = $poNumber;
        $globalStock->last_po_id     = $poId;
        $globalStock->last_po_number = $poNumber;

        $this->applyStockDelta($poStock, -$shiftToGlobal, $poNumber, $reason);
        $this->applyStockDelta($globalStock, $shiftToGlobal, $poNumber, $reason);
    }

    public function index()
    {
        $purchaseOrders = PurchaseOrder::with(['supplier','items'])
            ->latest('id')
            ->paginate(10);

        return view('admin.purchase_orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get(['id','name']);
        return view('admin.purchase_orders.create', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $request->merge(['po_number' => trim((string) $request->input('po_number'))]);

        $stockSource = $request->input('stock_source', 'po');
        $stockSource = $stockSource === 'fob_full' ? 'fob_full' : 'po';

        $rules = [
            'supplier_id'             => ['required','exists:suppliers,id'],
            'stock_source'            => ['required', Rule::in(['po', 'fob_full'])],
            'po_number'               => [
                'required','string','max:100',
                Rule::unique('purchase_orders', 'po_number')
                    ->where(fn($q) => $q->where('supplier_id', $request->input('supplier_id'))),
            ],
            'notes'                   => ['nullable','string'],
            'target_completion_date'  => ['nullable','date'],
        ];

        if ($stockSource === 'fob_full') {
            // FULL FOB: fokus No PO + Style saja (tanpa item material/receipt)
            $rules['styles']                  = ['required', 'array', 'min:1'];
            $rules['styles.*.style_name']     = ['required', 'string', 'max:100'];
            $rules['styles.*.style_quantity'] = ['required', 'integer', 'min:1'];

            // Item material tidak wajib (akan diabaikan)
            $rules['items'] = ['nullable', 'array'];
        } else {
            // PO normal: item material wajib, styles opsional
            $rules['items']                    = ['required', 'array', 'min:1'];
            $rules['items.*.material_code']    = ['nullable', 'string', 'max:64'];
            $rules['items.*.material_name']    = ['required', 'string', 'max:255'];
            $rules['items.*.unit']             = ['required', 'string', 'max:50'];
            $rules['items.*.ordered_quantity'] = ['required', 'numeric', 'min:0.0001'];

            $rules['styles']                  = ['nullable', 'array'];
            $rules['styles.*.style_name']     = ['required_with:styles', 'string', 'max:100'];
            $rules['styles.*.style_quantity'] = ['required_with:styles', 'integer', 'min:1'];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create([
                'supplier_id'            => $data['supplier_id'],
                'stock_source'           => $data['stock_source'] ?? 'po',
                'po_number'              => $data['po_number'],
                'notes'                  => $data['notes'] ?? null,
                'target_completion_date' => $data['target_completion_date'] ?? null,
                'is_completed'           => false,
            ]);

            // Items hanya untuk PO normal
            if (($data['stock_source'] ?? 'po') !== 'fob_full') {
                foreach ($data['items'] as $row) {
                    PurchaseOrderItem::create([
                        'purchase_order_id'        => $po->id,
                        'material_code'            => trim((string)($row['material_code'] ?? '')) ?: null,
                        'material_name'            => trim((string)$row['material_name']),
                        'unit'                     => trim((string)$row['unit']),
                        'ordered_quantity'         => (float) $row['ordered_quantity'],
                        'actual_arrived_quantity'  => 0.0,
                    ]);
                }
            }

            // Styles (jika ada)
            if (!empty($data['styles'])) {
                foreach ($data['styles'] as $row) {
                    if (!isset($row['style_name']) || $row['style_name'] === '') {
                        continue;
                    }

                    PurchaseOrderStyle::create([
                        'purchase_order_id' => $po->id,
                        'style_name'        => trim((string)$row['style_name']),
                        'style_quantity'    => (int) $row['style_quantity'],
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.purchase-orders.index')
            ->with('success', 'Purchase Order berhasil dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'supplier',
            'items' => fn($q) => $q->orderBy('material_name'),
            'items.receiptItems',
            'styles',
        ]);

        // Riwayat Penerimaan (pertanggal)
        $receipts = PurchaseReceipt::with(['items'])
            ->where('purchase_order_id', $purchaseOrder->id)
            ->orderByDesc('receipt_date')
            ->get();

        $receiptSummaries = $receipts->map(function ($receipt) {
            $totalQty  = $receipt->items->sum('received_quantity');
            $itemCount = $receipt->items->count();
            return [
                'receipt' => $receipt,
                'summary' => "{$itemCount} item, total diterima: {$totalQty}",
            ];
        });

        return view('admin.purchase_orders.show', [
            'purchaseOrder'     => $purchaseOrder,
            'receiptSummaries'  => $receiptSummaries,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        // SEKARANG: PO tetap boleh di-edit,
        // aturan lock ada di method update (per item).
        $suppliers = Supplier::orderBy('name')->get(['id','name']);
        $purchaseOrder->load(['items', 'styles']);

        return view('admin.purchase_orders.edit', compact('purchaseOrder', 'suppliers'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->merge(['po_number' => trim((string) $request->input('po_number'))]);

        $rules = [
            'supplier_id'             => ['required','exists:suppliers,id'],
            'po_number'               => [
                'required','string','max:100',
                Rule::unique('purchase_orders', 'po_number')
                    ->where(fn($q) => $q->where('supplier_id', $request->input('supplier_id')))
                    ->ignore($purchaseOrder->id),
            ],
            'notes'                   => ['nullable','string'],
            'target_completion_date'  => ['nullable','date'],
        ];

        if ($purchaseOrder->isFullFob()) {
            $rules['styles']                  = ['required', 'array', 'min:1'];
            $rules['styles.*.style_name']     = ['required', 'string', 'max:100'];
            $rules['styles.*.style_quantity'] = ['required', 'integer', 'min:1'];
        } else {
            $rules['items']                    = ['required','array','min:1'];
            $rules['items.*.id']               = ['nullable','integer','exists:purchase_order_items,id'];
            $rules['items.*.material_code']    = ['nullable','string','max:64'];
            $rules['items.*.material_name']    = ['required','string','max:255'];
            $rules['items.*.unit']             = ['required','string','max:50'];
            $rules['items.*.ordered_quantity'] = ['required','numeric','min:0.0001'];

            $rules['styles']                  = ['nullable','array'];
            $rules['styles.*.style_name']     = ['required_with:styles','string','max:100'];
            $rules['styles.*.style_quantity'] = ['required_with:styles','integer','min:1'];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($purchaseOrder, $data) {
            DB::table('purchase_orders')
                ->where('id', $purchaseOrder->id)
                ->lockForUpdate()
                ->value('id');

            $hasPostedReceipts = $purchaseOrder->hasPostedReceipt();

            // Kalau sudah ada RECEIPT POSTED → tidak boleh ganti supplier & no PO
            if ($hasPostedReceipts) {
                if ((int)$data['supplier_id'] !== (int)$purchaseOrder->supplier_id) {
                    throw ValidationException::withMessages([
                        'supplier_id' => 'Supplier tidak boleh diubah karena PO ini sudah memiliki penerimaan berstatus POSTED.',
                    ]);
                }

                if ($data['po_number'] !== $purchaseOrder->po_number) {
                    throw ValidationException::withMessages([
                        'po_number' => 'Nomor PO tidak boleh diubah karena PO ini sudah memiliki penerimaan berstatus POSTED.',
                    ]);
                }
            }

            // Update header PO
            $purchaseOrder->update([
                'supplier_id'            => $hasPostedReceipts ? $purchaseOrder->supplier_id : $data['supplier_id'],
                'po_number'              => $hasPostedReceipts ? $purchaseOrder->po_number : $data['po_number'],
                'notes'                  => $data['notes'] ?? null,
                'target_completion_date' => $data['target_completion_date'] ?? null,
            ]);

            if ($purchaseOrder->isFullFob()) {
                // FULL FOB: hanya update Styles, tanpa sync item material
                $purchaseOrder->styles()->delete();

                foreach ($data['styles'] as $row) {
                    if (!isset($row['style_name']) || $row['style_name'] === '') {
                        continue;
                    }

                    PurchaseOrderStyle::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'style_name'        => trim((string)$row['style_name']),
                        'style_quantity'    => (int)$row['style_quantity'],
                    ]);
                }

                return;
            }

            // ====== SYNC ITEMS TANPA DELETE MASSAL ======
            $existingItems = $purchaseOrder->items()->get()->keyBy('id');
            $keptIds       = [];

            foreach ($data['items'] as $row) {
                $rowId        = isset($row['id']) ? (int)$row['id'] : null;
                $materialCode = trim((string)($row['material_code'] ?? '')) ?: null;
                $materialName = trim((string)$row['material_name']);
                $unit         = trim((string)$row['unit']);
                $orderedQty   = (float) $row['ordered_quantity'];

                if ($rowId && $existingItems->has($rowId)) {
                    // === UPDATE ITEM LAMA ===
                    /** @var \App\Models\PurchaseOrderItem $item */
                    $item     = $existingItems[$rowId];
                    $keptIds[] = $rowId;

                    $hasPosted = $item->hasPostedReceipt();

                    if ($hasPosted) {
                        // Hitung total yang sudah diterima (hanya POSTED)
                        $actualPosted = (float) $item->postedReceiptItems()->sum('received_quantity');
                        $currentOrdered = (float) $item->ordered_quantity;
                        $qtyChanged = abs($orderedQty - $currentOrdered) > 0.0000001;

                        // Hanya boleh ubah ordered_quantity, identitas barang jangan diubah
                        if ($qtyChanged) {
                            $this->rebalancePostedItemStocksOnOrderedQtyChange(
                                $purchaseOrder,
                                $item,
                                $currentOrdered,
                                $orderedQty,
                                $actualPosted
                            );

                            $item->ordered_quantity = $orderedQty;
                            $item->save();
                        }
                    } else {
                        // Belum punya receipt POSTED → boleh ubah identitas & qty
                        $item->update([
                            'material_code'    => $materialCode,
                            'material_name'    => $materialName,
                            'unit'             => $unit,
                            'ordered_quantity' => $orderedQty,
                            // actual_arrived_quantity biarkan; akan diisi oleh proses posting
                        ]);
                    }
                } else {
                    // === ITEM BARU ===
                    $new = PurchaseOrderItem::create([
                        'purchase_order_id'       => $purchaseOrder->id,
                        'material_code'           => $materialCode,
                        'material_name'           => $materialName,
                        'unit'                    => $unit,
                        'ordered_quantity'        => $orderedQty,
                        'actual_arrived_quantity' => 0.0,
                    ]);

                    $keptIds[] = $new->id;
                }
            }

            // === HAPUS ITEM yang tidak dikirim di form, asal belum punya receipt apa pun ===
            $idsToDelete = $existingItems->keys()->diff($keptIds);

            foreach ($idsToDelete as $deleteId) {
                /** @var \App\Models\PurchaseOrderItem $item */
                $item = $existingItems[$deleteId];

                if ($item->hasAnyReceipt()) {
                    throw ValidationException::withMessages([
                        'items' => "Item '{$item->material_name}' tidak bisa dihapus karena sudah memiliki data penerimaan.",
                    ]);
                }

                $item->delete();
            }

            // Styles tetap bisa di-reset & buat ulang (tidak terkait langsung dengan receipt)
            $purchaseOrder->styles()->delete();

            if (!empty($data['styles'])) {
                foreach ($data['styles'] as $row) {
                    if (!isset($row['style_name']) || $row['style_name'] === '') {
                        continue;
                    }

                    PurchaseOrderStyle::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'style_name'        => trim((string)$row['style_name']),
                        'style_quantity'    => (int)$row['style_quantity'],
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.purchase-orders.index')
            ->with('success', 'Purchase Order berhasil diperbarui.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        // Masih tetap: kalau sudah ada receipt POSTED, jangan boleh dihapus
        if ($purchaseOrder->hasPostedReceipt()) {
            return redirect()
                ->route('admin.purchase-orders.show', $purchaseOrder->id)
                ->with(
                    'warning',
                    'Purchase Order ini sudah memiliki penerimaan berstatus POSTED sehingga tidak boleh dihapus.'
                );
        }

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->items()->delete();
            $purchaseOrder->styles()->delete();
            $purchaseOrder->delete();
        });

        return redirect()
            ->route('admin.purchase-orders.index')
            ->with('success', 'Purchase Order berhasil dihapus.');
    }
}
