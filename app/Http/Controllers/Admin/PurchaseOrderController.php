<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        // normalisasi ringan
        $request->merge(['po_number' => trim((string) $request->input('po_number'))]);

        $data = $request->validate([
            'supplier_id'             => ['required','exists:suppliers,id'],
            'po_number'               => [
                'required','string','max:100',
                Rule::unique('purchase_orders', 'po_number')
                    ->where(fn($q) => $q->where('supplier_id', $request->input('supplier_id'))),
            ],
            'notes'                  => ['nullable','string'], 
            'arrival_date'            => ['nullable','date'],
            'target_completion_date'  => ['nullable','date','after_or_equal:arrival_date'],

            'items'                     => ['required','array','min:1'],
            'items.*.material_code'     => ['nullable','string','max:64'],
            'items.*.material_name'     => ['required','string','max:255'],
            'items.*.unit'              => ['required','string','max:50'],
            'items.*.ordered_quantity'  => ['required','numeric','min:0.0001'],
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
        ]);

        $draftReceipts = PurchaseReceipt::withCount('items')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('status', 'draft')
            ->orderByDesc('id')
            ->get(['id','purchase_order_id','receipt_date','receipt_number','status']);

        $totals = PurchaseReceiptItem::selectRaw('purchase_receipt_id, SUM(received_quantity) as total_qty')
            ->whereIn('purchase_receipt_id', $draftReceipts->pluck('id'))
            ->groupBy('purchase_receipt_id')
            ->pluck('total_qty', 'purchase_receipt_id');

        return view('admin.purchase_orders.show', [
            'purchaseOrder' => $purchaseOrder,
            'draftReceipts' => $draftReceipts,
            'totals'        => $totals,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        $suppliers = Supplier::orderBy('name')->get(['id','name']);
        $purchaseOrder->load('items');

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
            'notes'                  => ['nullable','string'], 
            'arrival_date'            => ['nullable','date'],
            'target_completion_date'  => ['nullable','date','after_or_equal:arrival_date'],

            'items'                     => ['required','array','min:1'],
            'items.*.material_code'     => ['nullable','string','max:64'],
            'items.*.material_name'     => ['required','string','max:255'],
            'items.*.unit'              => ['required','string','max:50'],
            'items.*.ordered_quantity'  => ['required','numeric','min:0.0001'],
        ]);

        DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update([
                'supplier_id'            => $data['supplier_id'],
                'po_number'              => $data['po_number'],
                'notes'                  => $data['notes'] ?? null, 
                'arrival_date'           => $data['arrival_date'] ?? null,
                'target_completion_date' => $data['target_completion_date'] ?? null,
            ]);

            // rebuild items agar sederhana
            $purchaseOrder->items()->delete();

            foreach ($data['items'] as $row) {
                PurchaseOrderItem::create([
                    'purchase_order_id'        => $purchaseOrder->id,
                    'material_code'            => trim((string)($row['material_code'] ?? '')) ?: null,
                    'material_name'            => trim((string)$row['material_name']),
                    'unit'                     => trim((string)$row['unit']),
                    'ordered_quantity'         => (float) $row['ordered_quantity'],
                    'actual_arrived_quantity'  => 0.0,
                ]);
            }
        });

        return redirect()
            ->route('admin.purchase-orders.index')
            ->with('success', 'Purchase Order berhasil diperbarui.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->items()->delete();
            $purchaseOrder->delete();
        });

        return redirect()
            ->route('admin.purchase-orders.index')
            ->with('success', 'Purchase Order berhasil dihapus.');
    }
}
