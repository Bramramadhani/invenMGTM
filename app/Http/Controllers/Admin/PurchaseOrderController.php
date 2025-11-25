<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderStyle;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
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

        $data = $request->validate([
            'supplier_id'             => ['required','exists:suppliers,id'],
            'po_number'               => [
                'required','string','max:100',
                Rule::unique('purchase_orders', 'po_number')
                    ->where(fn($q) => $q->where('supplier_id', $request->input('supplier_id'))),
            ],
            'notes'                   => ['nullable','string'],
            'arrival_date'            => ['nullable','date'],
            'target_completion_date'  => ['nullable','date','after_or_equal:arrival_date'],

            'items'                     => ['required','array','min:1'],
            'items.*.material_code'     => ['nullable','string','max:64'],
            'items.*.material_name'     => ['required','string','max:255'],
            'items.*.unit'              => ['required','string','max:50'],
            'items.*.ordered_quantity'  => ['required','numeric','min:0.0001'],

            // Styles (opsional)
            'styles'                      => ['nullable','array'],
            'styles.*.style_name'         => ['required_with:styles','string','max:100'],
            'styles.*.style_quantity'     => ['required_with:styles','integer','min:1'],
        ]);

        DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create([
                'supplier_id'            => $data['supplier_id'],
                'po_number'              => $data['po_number'],
                'notes'                  => $data['notes'] ?? null,
                'arrival_date'           => $data['arrival_date'] ?? null,
                'target_completion_date' => $data['target_completion_date'] ?? null,
                'is_completed'           => false,
            ]);

            // Items
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

        $data = $request->validate([
            'supplier_id'             => ['required','exists:suppliers,id'],
            'po_number'               => [
                'required','string','max:100',
                Rule::unique('purchase_orders', 'po_number')
                    ->where(fn($q) => $q->where('supplier_id', $request->input('supplier_id')))
                    ->ignore($purchaseOrder->id),
            ],
            'notes'                   => ['nullable','string'],
            'arrival_date'            => ['nullable','date'],
            'target_completion_date'  => ['nullable','date','after_or_equal:arrival_date'],

            'items'                     => ['required','array','min:1'],
            'items.*.id'                => ['nullable','integer','exists:purchase_order_items,id'],
            'items.*.material_code'     => ['nullable','string','max:64'],
            'items.*.material_name'     => ['required','string','max:255'],
            'items.*.unit'              => ['required','string','max:50'],
            'items.*.ordered_quantity'  => ['required','numeric','min:0.0001'],

            'styles'                      => ['nullable','array'],
            'styles.*.style_name'         => ['required_with:styles','string','max:100'],
            'styles.*.style_quantity'     => ['required_with:styles','integer','min:1'],
        ]);

        DB::transaction(function () use ($purchaseOrder, $data) {
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
                'arrival_date'           => $data['arrival_date'] ?? null,
                'target_completion_date' => $data['target_completion_date'] ?? null,
            ]);

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

                        if ($orderedQty < $actualPosted) {
                            throw ValidationException::withMessages([
                                'items' => "Qty PO untuk material '{$item->material_name}' tidak boleh kurang dari total yang sudah diterima ({$actualPosted}).",
                            ]);
                        }

                        // Hanya boleh ubah ordered_quantity, identitas barang jangan diubah
                        $item->ordered_quantity = $orderedQty;
                        $item->save();
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
