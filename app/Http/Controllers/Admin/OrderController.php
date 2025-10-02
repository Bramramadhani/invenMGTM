<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\ProductionIssue;
use App\Models\ProductionIssueItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                      ->orWhere('id', $q);
                });
            })
            ->latest('id')
            ->paginate(15);

        $suppliers = Supplier::orderBy('name')->get(['id','name']);

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
            ->get(['id','name']);

        return view('admin.order.create', compact('suppliers'));
    }

    // AJAX: daftar PO milik supplier yang punya stok > 0
    public function supplierPOs(Supplier $supplier)
    {
        $poIds = Stock::where('supplier_id', $supplier->id)
            ->whereNotNull('last_po_id')
            ->where('quantity', '>', 0)
            ->distinct()
            ->pluck('last_po_id');

        $pos = PurchaseOrder::whereIn('id', $poIds)
            ->orderByDesc('id')
            ->get(['id','po_number']);

        return response()->json([
            'supplier' => ['id' => $supplier->id, 'name' => $supplier->name],
            'pos'      => $pos->map(fn($po) => ['id' => $po->id, 'po_number' => $po->po_number])->values(),
        ]);
    }

    // AJAX: stok per-PO (qty > 0)
    public function poStocks(PurchaseOrder $purchaseOrder)
    {
        $rows = Stock::with('supplier')
            ->where('last_po_id', $purchaseOrder->id)
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
            'items' => $rows->map(function (Stock $s) {
                return [
                    'stock_id'       => $s->id,
                    'material_code'  => $s->material_code,
                    'material_name'  => $s->material_name,
                    'unit'           => $s->unit,
                    'supplier'       => optional($s->supplier)->name,
                    'available'      => (float) $s->quantity,
                ];
            }),
        ]);
    }

    /**
     * SIMPAN permintaan → auto POST OUT.
     * Hanya catatan per item (items.*.notes).
     */
    public function store(Request $request)
    {
        // Normalisasi qty (koma→titik, hilangkan spasi)
        $payload = $request->all();
        if (isset($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $i => $row) {
                if (isset($row['quantity']) && is_string($row['quantity'])) {
                    $v = trim(str_replace(' ', '', $row['quantity']));
                    $v = str_replace(',', '.', $v);
                    $parts = explode('.', $v);
                    if (count($parts) > 2) {
                        $v = $parts[0].'.'.implode('', array_slice($parts, 1));
                    }
                    $payload['items'][$i]['quantity'] = $v;
                }
            }
            $request->merge($payload);
        }

        $data = $request->validate([
            'items'             => ['required','array','min:1'],
            'items.*.stock_id'  => ['required','integer','exists:stocks,id'],
            'items.*.quantity'  => ['required','numeric','min:0.0001'],
            'items.*.notes'     => ['nullable','string','max:255'],
        ], [], [
            'items' => 'Item permintaan',
        ]);

        DB::transaction(function () use ($data) {
            // Nomor dokumen order
            $counter   = Order::lockForUpdate()->count() + 1;
            $orderName = 'REQ-' . now()->format('Ymd') . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);

            // 1) ORDER (auto selesai)
            $order = Order::create([
                'user_id' => auth()->id(),
                'status'  => $this->S('success'),
                'name'    => $orderName,
                'notes'   => null,
            ]);

            // 2) Production Issue (langsung posted)
            $issueCounter = ProductionIssue::lockForUpdate()->count() + 1;
            $issueNumber  = 'IS-' . now()->format('Ymd') . '-' . str_pad($issueCounter, 4, '0', STR_PAD_LEFT);

            $issue = ProductionIssue::create([
                'issue_date'   => now()->toDateString(),
                'issue_number' => $issueNumber,
                'notes'        => null,
                'status'       => 'posted',
                'posted_at'    => now(),
                'posted_by'    => Auth::id(),
                'order_id'     => $order->id,
            ]);

            foreach ($data['items'] as $row) {
                /** @var \App\Models\Stock $stock */
                $stock = Stock::with('supplier')->lockForUpdate()->findOrFail($row['stock_id']);

                $available = (float) $stock->quantity;
                $qty       = (float) $row['quantity'];
                $itemNote  = trim((string)($row['notes'] ?? ''));

                if ($qty <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items" => ["Qty harus > 0 untuk {$stock->material_name} (PO {$stock->last_po_number})."]
                    ]);
                }
                if ($qty > $available) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items" => ["Qty melebihi stok tersedia untuk {$stock->material_name} (PO {$stock->last_po_number}). Tersedia: {$available}."]
                    ]);
                }

                // a) OrderItem (jejak)
                $orderItem = OrderItem::create([
                    'order_id'       => $order->id,
                    'stock_id'       => $stock->id,
                    'supplier_id'    => $stock->supplier_id,
                    'material_code'  => $stock->material_code,
                    'material_name'  => $stock->material_name,
                    'unit'           => $stock->unit,
                    'quantity'       => $qty,
                    'notes'          => $itemNote ?: null,
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
                ]);

                // c) Kurangi stok
                $stock->decrement('quantity', $qty);

                // d) Movement OUT → hanya simpan notes kalau admin isi
                $movementNote = $itemNote !== '' ? $itemNote : null;

                StockMovement::recordOut(
                    $stock->id,
                    (int) $stock->supplier_id,
                    $stock->material_name,
                    $stock->unit,
                    (float) $qty,
                    $stock->last_po_number ?: null,
                    $movementNote, // null jika kosong
                    'production_issues',
                    (int) $issue->id,
                    now()
                );
            }
        });

        return redirect()
            ->route('admin.orders.index')
            ->with('success', 'Permintaan disimpan & stok langsung dikurangi. Material code ikut tersimpan.');
    }

    public function show(Order $order)
    {
        $order->load([
            'user',
            'items.stock.supplier',
        ]);
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

    /**
     * Generate & download PDF Receipt Permintaan Barang (bukti fisik)
     * Route: admin.orders.receipt-pdf
     */
    public function receiptPdf(Order $order)
    {
        $order->load(['user', 'items.stock.supplier']);

        $printedAtDate = optional($order->created_at)->format('d-m-Y') ?? now()->format('d-m-Y');
        $printedAtTime = optional($order->created_at)->format('H:i')    ?? now()->format('H:i');
        $adminName     = Auth::user()->name ?? optional($order->user)->name ?? '';
       
        $pdf = PDF::loadView('admin.order.pdf', [
            'order'         => $order,
            'printedAtDate' => $printedAtDate,
            'printedAtTime' => $printedAtTime,
            'adminName'     => $adminName,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Order_'.$order->name.'_Receipt.pdf');
    }
}
