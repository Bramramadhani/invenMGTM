<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStyle;
use App\Models\Stock;
use App\Models\ProductionIssue;
use App\Models\ProductionIssueItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class OrderController extends Controller
{
    private function S(string $key): string
    {
        return match (strtolower($key)) {
            'pending'  => 'Menunggu Konfirmasi',
            'verified' => 'Terverifikasi',
            'success'  => 'Selesai',
            default    => $key,
        };
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $orders = Order::with('user')
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('notes', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('production_name', 'like', "%{$q}%")
                        ->orWhere('production_leader_name', 'like', "%{$q}%")
                        ->orWhere('warehouse_admin_name', 'like', "%{$q}%")
                        ->orWhere('warehouse_leader_name', 'like', "%{$q}%")
                        ->orWhere('id', $q);
                });
            })
            ->latest('id')
            ->paginate(15);

        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        return view('admin.order.index', compact('orders', 'suppliers', 'q'));
    }

    public function create()
    {
        $supplierIds = Stock::where('quantity', '>', 0)
            ->distinct()
            ->pluck('supplier_id')
            ->filter();

        $suppliers = Supplier::whereIn('id', $supplierIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.order.create', compact('suppliers'));
    }

    // AJAX: daftar PO milik supplier yang punya stok > 0 (STRICT, tanpa legacy)
    public function supplierPOs(Supplier $supplier)
    {
        $poIds = Stock::where('supplier_id', $supplier->id)
            ->whereNotNull('purchase_order_id')
            ->where('quantity', '>', 0)
            ->distinct()
            ->pluck('purchase_order_id');

        $pos = PurchaseOrder::whereIn('id', $poIds)
            ->orderByDesc('id')
            ->get(['id', 'po_number']);

        return response()->json([
            'supplier' => ['id' => $supplier->id, 'name' => $supplier->name],
            'pos'      => $pos->map(fn ($po) => [
                'id'        => $po->id,
                'po_number' => $po->po_number,
            ])->values(),
        ]);
    }

    // AJAX: stok per-PO (qty > 0) — STRICT
    public function poStocks(PurchaseOrder $purchaseOrder)
    {
        $rows = Stock::with(['supplier'])
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('quantity', '>', 0)
            ->orderBy('material_name')
            ->orderBy('unit')
            ->get();

        return response()->json([
            'po'    => [
                'id'   => $purchaseOrder->id,
                'no'   => $purchaseOrder->po_number,
                'supp' => optional($purchaseOrder->supplier)->name,
            ],
            'items' => $rows->map(function (Stock $s) use ($purchaseOrder) {
                return [
                    'stock_id'      => $s->id,
                    'material_code' => $s->material_code,
                    'material_name' => $s->material_name,
                    'unit'          => $s->unit,
                    'supplier'      => optional($s->supplier)->name,
                    'po_number'     => $purchaseOrder->po_number,
                    'available'     => (float) $s->quantity,
                ];
            }),
        ]);
    }

    /**
     * AJAX – daftar Styles milik satu PO (untuk dropdown Style)
     * SEKARANG: hanya kirim id & name saja (tanpa qty / label),
     * supaya di dropdown tidak ada teks "0 tas" atau "null".
     */
    public function poStyles(PurchaseOrder $purchaseOrder)
    {
        $styles = $purchaseOrder->styles()
            ->orderBy('id')
            ->get();

        return response()->json([
            'po'     => [
                'id' => $purchaseOrder->id,
                'no' => $purchaseOrder->po_number,
            ],
            'styles' => $styles->map(function ($st) {
                $name = $st->style_name
                    ?? $st->name
                    ?? $st->nama_style
                    ?? ('Style #' . $st->id);

                return [
                    'id'   => $st->id,
                    'name' => $name,
                ];
            })->values(),
        ]);
    }

    /**
     * Simpan permintaan barang (langsung auto-post stok keluar)
     */
    public function store(Request $request)
    {
        // Normalisasi qty koma→titik
        $payload = $request->all();
        if (isset($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $i => $row) {
                if (isset($row['quantity']) && is_string($row['quantity'])) {
                    $v = trim(str_replace(' ', '', $row['quantity']));
                    $v = str_replace(',', '.', $v);
                    $parts = explode('.', $v);
                    if (count($parts) > 2) {
                        $v = $parts[0] . '.' . implode('', array_slice($parts, 1));
                    }
                    $payload['items'][$i]['quantity'] = $v;
                }
            }
            $request->merge($payload);
        }

        $data = $request->validate([
            'production_name'         => ['required', 'string', 'max:255'],
            'production_leader_name'  => ['required', 'string', 'max:255'],
            'warehouse_admin_name'    => ['required', 'string', 'max:255'],
            'warehouse_leader_name'   => ['required', 'string', 'max:255'],
            'supply_chain_head_name'  => ['nullable', 'string', 'max:191'],

            // 1 permintaan = 1 style
            'purchase_order_style_id' => ['required', 'integer', 'exists:purchase_order_styles,id'],

            'items'            => ['required', 'array', 'min:1'],
            'items.*.stock_id' => ['required', 'integer', 'exists:stocks,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.notes'    => ['nullable', 'string', 'max:255'],
        ], [], [
            'items'                   => 'Item permintaan',
            'purchase_order_style_id' => 'Style PO',
        ]);

        DB::transaction(function () use ($data) {
            $now = now();

            /** @var \App\Models\PurchaseOrderStyle $style */
            $style = PurchaseOrderStyle::with('purchaseOrder')
                ->lockForUpdate()
                ->findOrFail($data['purchase_order_style_id']);

            $stylePo      = $style->purchaseOrder;
            $stylePoId    = optional($stylePo)->id;
            $stylePoNo    = optional($stylePo)->po_number;

            // Nomor dokumen order
            $counter   = Order::lockForUpdate()->count() + 1;
            $orderName = 'REQ-' . $now->format('Ymd') . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);

            // 1) ORDER
            $order = Order::create([
                'user_id'                 => auth()->id(),
                'status'                  => $this->S('success'),
                'name'                    => $orderName,
                'notes'                   => null,
                'production_name'         => $data['production_name'],
                'production_leader_name'  => $data['production_leader_name'],
                'warehouse_admin_name'    => $data['warehouse_admin_name'],
                'warehouse_leader_name'   => $data['warehouse_leader_name'],
                'supply_chain_head_name'  => $data['supply_chain_head_name'] ?? null,
                'purchase_order_style_id' => $style->id,
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);

            // 2) Production Issue (posted)
            $issueCounter = ProductionIssue::lockForUpdate()->count() + 1;
            $issueNumber  = 'IS-' . $now->format('Ymd') . '-' . str_pad($issueCounter, 4, '0', STR_PAD_LEFT);

            $issue = ProductionIssue::create([
                'issue_date'             => $now->toDateString(),
                'issue_number'           => $issueNumber,
                'notes'                  => null,
                'status'                 => 'posted',
                'posted_at'              => $now,
                'posted_by'              => Auth::id(),
                'order_id'               => $order->id,
                'requested_at'           => $now,
                'requested_by'           => Auth::id(),
                'purchase_order_style_id'=> $style->id,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);

            // 3) Items
            foreach ($data['items'] as $row) {
                $stock = Stock::with(['supplier', 'purchaseOrder'])
                    ->lockForUpdate()
                    ->findOrFail($row['stock_id']);

                $available = (float) $stock->quantity;
                $qty       = (float) $row['quantity'];
                $itemNote  = trim((string)($row['notes'] ?? ''));

                // stok harus dari PO yang sama dengan style
                if (!empty($stock->purchase_order_id) && $stylePoId && $stock->purchase_order_id !== $stylePoId) {
                    throw ValidationException::withMessages([
                        'items' => ["Item {$stock->material_name} bukan dari PO {$stylePoNo}. Harus satu PO dengan Style yang dipilih."],
                    ]);
                }

                if ($qty <= 0 || $qty > $available) {
                    throw ValidationException::withMessages([
                        'items' => ["Qty tidak valid untuk {$stock->material_name} (tersedia: {$available})"],
                    ]);
                }

                // a) OrderItem
                $orderItem = OrderItem::create([
                    'order_id'       => $order->id,
                    'stock_id'       => $stock->id,
                    'supplier_id'    => $stock->supplier_id,
                    'material_code'  => $stock->material_code,
                    'material_name'  => $stock->material_name,
                    'unit'           => $stock->unit,
                    'quantity'       => $qty,
                    'notes'          => $itemNote ?: null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);

                // b) ProductionIssueItem
                ProductionIssueItem::create([
                    'production_issue_id' => $issue->id,
                    'order_item_id'       => $orderItem->id,
                    'stock_id'            => $stock->id,
                    'supplier_id'         => $stock->supplier_id,
                    'material_code'       => $stock->material_code,
                    'material_name'       => $stock->material_name,
                    'unit'                => $stock->unit,
                    'quantity'            => $qty,
                    'notes'               => $itemNote ?: null,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);

                // c) Kurangi stok
                $stock->decrement('quantity', $qty);

                // d) Movement OUT
                $poNumber = optional($stock->purchaseOrder)->po_number;

                StockMovement::recordOut(
                    stockId:       $stock->id,
                    supplierId:    (int) $stock->supplier_id,
                    material:      $stock->material_name,
                    unit:          $stock->unit,
                    qty:           (float) $qty,
                    poNumber:      $poNumber ?: null,
                    notes:         $itemNote ?: null,
                    movedAt:       $now,
                    orderId:       $order->id,
                    orderItemId:   $orderItem->id,
                );
            }
        });

        return redirect()
            ->route('admin.orders.index')
            ->with('success', 'Permintaan disimpan dan stok langsung dikurangi.');
    }

    public function show(Order $order)
    {
        $order->load(['user', 'items.stock.supplier', 'purchaseOrderStyle']);
        return view('admin.order.show', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        return back()->with('info', 'Permintaan sudah auto-posted saat disimpan.');
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return back()->with('success', 'Permintaan dihapus.');
    }

    public function receiptPdf(Order $order)
    {
        $order->load(['user', 'items.stock.supplier', 'purchaseOrderStyle']);

        $printedAtDate = optional($order->created_at)->format('d-m-Y') ?? now()->format('d-m-Y');
        $printedAtTime = optional($order->created_at)->format('H:i') ?? now()->format('H:i');
        $adminName     = Auth::user()->name ?? optional($order->user)->name ?? '';

        $pdf = PDF::loadView('admin.order.pdf', [
            'order'         => $order,
            'printedAtDate' => $printedAtDate,
            'printedAtTime' => $printedAtTime,
            'adminName'     => $adminName,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Order_' . $order->name . '_Receipt.pdf');
    }
}
