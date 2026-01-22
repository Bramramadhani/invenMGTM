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
use App\Models\Buyer;
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

    private function unitMultiplier(?string $unit): float
    {
        $u = strtolower(trim((string) $unit));
        return in_array($u, ['lusin', 'lusinan', 'dozen', 'dz'], true) ? 12.0 : 1.0;
    }

    private function toBaseQty(float $qty, ?string $unit): float
    {
        return $qty * $this->unitMultiplier($unit);
    }

    private function fromBaseQty(float $qty, ?string $unit): float
    {
        $mult = $this->unitMultiplier($unit);
        return $mult > 0 ? $qty / $mult : $qty;
    }

    private function displayUnit(?string $unit): string
    {
        return $this->unitMultiplier($unit) > 1 ? 'PCS' : (string) $unit;
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $orders = Order::with(['user', 'buyer'])
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

        // Saat ini belum dipakai di view, tapi dibiarkan untuk future filter
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        return view('admin.order.index', compact('orders', 'suppliers', 'q'));
    }

    /**
     * Form Buat Permintaan.
     * Mendukung 2 mode sumber stok:
     * - source_type = 'po'  → stok per-PO (supplier)
     * - source_type = 'fob' → stok FOB (Buyer)
     */
    public function create()
    {
        // Semua Supplier (target PO)
        $suppliers = Supplier::orderBy('name')
            ->get(['id', 'name']);

        // Semua Buyer (sumber stok FOB)
        $buyers = Buyer::orderBy('name')
            ->get(['id', 'name']);

        return view('admin.order.create', compact('suppliers', 'buyers'));
    }

    /**
     * AJAX: daftar PO milik supplier.
     * - mode=po  → hanya PO yang punya stok normal > 0 (seperti sebelumnya)
     * - mode=fob → semua PO milik supplier (untuk kebutuhan target PO permintaan FOB)
     */
    public function supplierPOs(Supplier $supplier)
    {
        $mode = request()->get('mode'); // 'po' atau 'fob'

        if ($mode === 'fob') {
            // Untuk permintaan FOB: semua PO milik supplier ini (tanpa cek stok)
            $posQuery = PurchaseOrder::where('supplier_id', $supplier->id);
        } else {
            // Untuk permintaan stok PO biasa: hanya PO yang punya stok normal > 0
            $poIds = Stock::where('supplier_id', $supplier->id)
                ->whereNull('buyer_id') // stok normal saja
                ->whereNotNull('purchase_order_id')
                ->where('quantity', '>', 0)
                ->distinct()
                ->pluck('purchase_order_id');

            $posQuery = PurchaseOrder::whereIn('id', $poIds);
        }

        $pos = $posQuery
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

    /**
     * AJAX: stok per-PO (qty > 0) — stok normal (bukan FOB).
     */
    public function poStocks(PurchaseOrder $purchaseOrder)
    {
        $rows = Stock::with(['supplier', 'buyer'])
            ->whereNull('buyer_id') // stok normal saja
            ->where('quantity', '>', 0)
            ->where(function ($q) use ($purchaseOrder) {
                $q->where('purchase_order_id', $purchaseOrder->id)
                  ->orWhere(function ($qq) use ($purchaseOrder) {
                      $qq->whereNull('purchase_order_id')
                         ->where('supplier_id', $purchaseOrder->supplier_id);
                  });
            })
            ->orderByRaw('purchase_order_id is null')
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
                $supplierName  = optional($s->supplier)->name;
                $buyerName     = optional($s->buyer)->name;
                $sourceLabel   = $supplierName ?: ($buyerName ?: '-');
                $displayUnit   = $this->displayUnit($s->unit);
                $availableBase = $this->toBaseQty((float) $s->quantity, $s->unit);
                $isGlobal      = $s->purchase_order_id === null;

                return [
                    'stock_id'      => $s->id,
                    'material_code' => $s->material_code,
                    'material_name' => $s->material_name,
                    'unit'          => $displayUnit,
                    'vendor_name'   => $s->vendor_name,
                    'supplier'      => $supplierName,
                    'buyer'         => $buyerName,
                    'buyer_id'      => $s->buyer_id,
                    'source_label'  => $sourceLabel,
                    'source_type'   => 'po',
                    'po_number'     => $isGlobal ? 'GLOBAL' : $purchaseOrder->po_number,
                    'available'     => $availableBase,
                    'is_global'     => $isGlobal,
                ];
            }),
        ]);
    }

    /**
     * AJAX: stok FOB per-Buyer (qty > 0).
     */
    public function buyerStocks(Buyer $buyer)
    {
        $rows = Stock::with(['supplier', 'buyer', 'purchaseOrder'])
            ->where('buyer_id', $buyer->id)
            ->where('quantity', '>', 0)
            ->orderBy('material_name')
            ->orderBy('unit')
            ->get();

        return response()->json([
            'buyer' => [
                'id'   => $buyer->id,
                'name' => $buyer->name,
            ],
            'items' => $rows->map(function (Stock $s) {
                $supplierName = optional($s->supplier)->name;
                $buyerName    = optional($s->buyer)->name;
                $sourceLabel  = $buyerName ?: ($supplierName ?: '-');
                $displayUnit  = $this->displayUnit($s->unit);
                $availableBase = $this->toBaseQty((float) $s->quantity, $s->unit);

                return [
                    'stock_id'      => $s->id,
                    'material_code' => $s->material_code,
                    'material_name' => $s->material_name,
                    'unit'          => $displayUnit,
                    'vendor_name'   => $s->vendor_name,
                    'supplier'      => $supplierName,
                    'buyer'         => $buyerName,
                    'buyer_id'      => $s->buyer_id,
                    'source_label'  => $sourceLabel,
                    'source_type'   => 'fob',
                    'po_number'     => optional($s->purchaseOrder)->po_number,
                    'available'     => $availableBase,
                ];
            }),
        ]);
    }

    /**
     * AJAX – daftar Styles milik satu PO (untuk dropdown Style).
     * Dipakai oleh mode PO maupun FOB (sebagai "target Style").
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
     * - source_type = 'po'  → stok normal per-PO
     * - source_type = 'fob' → stok FOB (Buyer), tapi tetap untuk 1 Style PO
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
            'source_type'             => ['required', 'in:po,fob,mixed'],
            'buyer_id'                => ['nullable', 'integer', 'exists:buyers,id'],

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
            'buyer_id'                => 'Buyer',
        ]);

        // Kalau mode FOB / Mixed, buyer wajib diisi
        $sourceType = $data['source_type'] ?? 'po';
        if (in_array($sourceType, ['fob', 'mixed'], true) && empty($data['buyer_id'])) {
            throw ValidationException::withMessages([
                'buyer_id' => ['Buyer wajib dipilih untuk permintaan dari stok FOB.'],
            ]);
        }

        DB::transaction(function () use ($data) {
            $now        = now();
            $sourceType = $data['source_type'] ?? 'po';
            $buyerId    = $data['buyer_id'] ?? null;

            /** @var \App\Models\PurchaseOrderStyle $style */
            $style = PurchaseOrderStyle::with('purchaseOrder')
                ->lockForUpdate()
                ->findOrFail($data['purchase_order_style_id']);

            $stylePo   = $style->purchaseOrder;
            $stylePoId = optional($stylePo)->id;
            $stylePoNo = optional($stylePo)->po_number;

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
                'source_type'             => $sourceType,
                'buyer_id'                => in_array($sourceType, ['fob', 'mixed'], true) ? $buyerId : null,
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);

            // 2) Production Issue (posted)
            $issueCounter = ProductionIssue::lockForUpdate()->count() + 1;
            $issueNumber  = 'IS-' . $now->format('Ymd') . '-' . str_pad($issueCounter, 4, '0', STR_PAD_LEFT);

            $issue = ProductionIssue::create([
                'issue_date'              => $now->toDateString(),
                'issue_number'            => $issueNumber,
                'notes'                   => null,
                'status'                  => 'posted',
                'posted_at'               => $now,
                'posted_by'               => Auth::id(),
                'order_id'                => $order->id,
                'requested_at'            => $now,
                'requested_by'            => Auth::id(),
                'purchase_order_style_id' => $style->id,
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);

            // 3) Items
            $createdItems = 0;

            foreach ($data['items'] as $row) {
                $stock = Stock::with(['supplier', 'purchaseOrder', 'buyer'])
                    ->lockForUpdate()
                    ->findOrFail($row['stock_id']);

                $displayUnit   = $this->displayUnit($stock->unit);
                $availableBase = $this->toBaseQty((float) $stock->quantity, $stock->unit);
                $qtyInput      = (float) $row['quantity'];
                $qtyBase       = $this->toBaseQty($qtyInput, $displayUnit);
                $qtyStock      = $this->fromBaseQty($qtyBase, $stock->unit);
                $itemNote      = trim((string)($row['notes'] ?? ''));

                // supplier_id untuk dokumen (boleh null untuk FOB)
                $supplierIdForDocs     = $stock->supplier_id;
                // supplier_id untuk log movement (fallback 0 jika null)
                $supplierIdForMovement = (int) ($stock->supplier_id ?? 0);

                // Validasi jenis stok vs source_type
                if ($sourceType === 'po') {
                    // Harus stok normal (bukan FOB)
                    if (!is_null($stock->buyer_id)) {
                        throw ValidationException::withMessages([
                            'items' => ["Item {$stock->material_name} adalah stok FOB (Buyer). Gunakan mode permintaan FOB untuk stok tersebut."],
                        ]);
                    }

                    // stok harus dari PO yang sama dengan style
                    if (!empty($stock->purchase_order_id) && $stylePoId && $stock->purchase_order_id !== $stylePoId) {
                        throw ValidationException::withMessages([
                            'items' => ["Item {$stock->material_name} bukan dari PO {$stylePoNo}. Harus satu PO dengan Style yang dipilih."],
                        ]);
                    }
                } elseif ($sourceType === 'fob') {
                    // Mode FOB: wajib stok FOB dari buyer yang dipilih
                    if (empty($stock->buyer_id) || ($buyerId && (int) $stock->buyer_id !== (int) $buyerId)) {
                        // Ada item "nyasar" (mis. sisa dari tab lain) -> abaikan saja, jangan bikin seluruh permintaan gagal
                        continue;
                    }
                    // purchase_order_id stok FOB boleh null / beda PO, karena "target"-nya diwakili Style PO
                } else {
                    // Mode Mixed: boleh gabung stok PO/Global + FOB (1 buyer)
                    if (!is_null($stock->buyer_id)) {
                        if (!$buyerId || (int) $stock->buyer_id !== (int) $buyerId) {
                            throw ValidationException::withMessages([
                                'items' => ["Item {$stock->material_name} bukan stok FOB dari Buyer yang sama dengan header Order."],
                            ]);
                        }
                    } else {
                        if (!empty($stock->purchase_order_id) && $stylePoId && $stock->purchase_order_id !== $stylePoId) {
                            throw ValidationException::withMessages([
                                'items' => ["Item {$stock->material_name} bukan dari PO {$stylePoNo}. Harus satu PO dengan Style yang dipilih."],
                            ]);
                        }

                        if (empty($stock->purchase_order_id) && $stylePo && (int) $stock->supplier_id !== (int) $stylePo->supplier_id) {
                            throw ValidationException::withMessages([
                                'items' => ["Item {$stock->material_name} bukan stok global untuk Buyer yang sama dengan PO."],
                            ]);
                        }
                    }
                }

                // Validasi qty hanya untuk item yang benar-benar dipakai
                if ($qtyBase <= 0 || $qtyBase > $availableBase) {
                    throw ValidationException::withMessages([
                        'items' => ["Qty tidak valid untuk {$stock->material_name} (tersedia: {$availableBase})"],
                    ]);
                }

                // a) OrderItem
                $orderItem = OrderItem::create([
                    'order_id'       => $order->id,
                    'stock_id'       => $stock->id,
                    'supplier_id'    => $supplierIdForDocs,
                    'material_code'  => $stock->material_code,
                    'material_name'  => $stock->material_name,
                    'unit'           => $displayUnit,
                    'quantity'       => $qtyBase,
                    'notes'          => $itemNote ?: null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);

                // b) ProductionIssueItem
                ProductionIssueItem::create([
                    'production_issue_id' => $issue->id,
                    'order_item_id'       => $orderItem->id,
                    'stock_id'            => $stock->id,
                    'supplier_id'         => $supplierIdForDocs,
                    'material_code'       => $stock->material_code,
                    'material_name'       => $stock->material_name,
                    'unit'                => $displayUnit,
                    'quantity'            => $qtyBase,
                    'notes'               => $itemNote ?: null,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);

                // c) Kurangi stok
                $stock->decrement('quantity', $qtyStock);

                // d) Movement OUT
                // Untuk kedua mode, poNumber di movement diarahkan ke PO dari Style (target produksi)
                $poNumberForMovement = $stylePoNo ?: optional($stock->purchaseOrder)->po_number;

                StockMovement::recordOut(
                    stockId:     $stock->id,
                    supplierId:  $supplierIdForMovement,
                    material:    $stock->material_name,
                    unit:        $displayUnit,
                    qty:         (float) $qtyBase,
                    poNumber:    $poNumberForMovement ?: null,
                    notes:       $itemNote ?: null,
                    movedAt:     $now,
                    orderId:     $order->id,
                    orderItemId: $orderItem->id,
                );

                $createdItems++;
            }

            if ($createdItems === 0) {
                // Tidak ada item valid yang diproses → rollback
                throw ValidationException::withMessages([
                    'items' => ['Tidak ada item valid yang bisa diproses. Pastikan minimal satu stok dipilih.'],
                ]);
            }
        });

        return redirect()
            ->route('admin.orders.index')
            ->with('success', 'Permintaan disimpan dan stok langsung dikurangi.');
    }

    public function show(Order $order)
    {
        $order->load([
            'user',
            'buyer',
            'items.stock.supplier',
            'items.stock.buyer',
            'items.stock.purchaseOrder',
            'purchaseOrderStyle.purchaseOrder',
        ]);

        return view('admin.order.show', compact('order'));
    }

    /**
     * Form Edit Permintaan
     * Style & PO dikunci (readonly).
     * Source_type & Buyer ditampilkan sebagai informasi (tidak bisa diubah).
     */
    public function edit(Order $order)
    {
        $order->load([
            'items.stock.supplier',
            'items.stock.buyer',
            'purchaseOrderStyle.purchaseOrder.supplier',
            'buyer',
            'user',
        ]);

        $sourceType = $order->source_type ?? 'po';
        $style = $order->purchaseOrderStyle;
        $po    = optional($style)->purchaseOrder;

        if (!$style || !$po) {
            return back()->with('warning', 'Order ini belum terkait Style/PO yang valid.');
        }

        return view('admin.order.edit', [
            'order' => $order,
            'style' => $style,
            'po'    => $po,
        ]);
    }

    /**
     * Update permintaan:
     * - Update header (nama-nama)
     * - Diff item lama vs baru
     * - Sesuaikan stok, ProductionIssueItem, dan StockMovement OUT
     *
     * Logika stok beda antara source_type:
     * - po  → stok normal & harus satu PO dengan Style
     * - fob → stok FOB & (buyer_id) harus dari buyer yang sama
     */
    public function update(Request $request, Order $order)
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

            'items'            => ['required', 'array', 'min:1'],
            'items.*.stock_id' => ['required', 'integer', 'exists:stocks,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.notes'    => ['nullable', 'string', 'max:255'],
        ], [], [
            'items' => 'Item permintaan',
        ]);

        DB::transaction(function () use ($order, $data) {
            $now = now();

            // Reload order dengan relasi yang dibutuhkan
            $order->load([
                'items',
                'purchaseOrderStyle.purchaseOrder',
                'buyer',
            ]);

            $style      = $order->purchaseOrderStyle;
            $po         = optional($style)->purchaseOrder;
            $sourceType = $order->source_type ?? 'po';
            $buyerId    = $order->buyer_id;

            if (!$style || !$po) {
                throw ValidationException::withMessages(['order' => 'Order belum terkait PO/Style.']);
            }
            if (in_array($sourceType, ['fob', 'mixed'], true) && empty($buyerId)) {
                throw ValidationException::withMessages([
                    'order' => 'Buyer belum diisi untuk permintaan FOB/Mixed.',
                ]);
            }

            // UPDATE header
            $order->update([
                'production_name'         => $data['production_name'],
                'production_leader_name'  => $data['production_leader_name'],
                'warehouse_admin_name'    => $data['warehouse_admin_name'],
                'warehouse_leader_name'   => $data['warehouse_leader_name'],
                'supply_chain_head_name'  => $data['supply_chain_head_name'] ?? null,
                'updated_at'              => $now,
            ]);

            // Ambil ProductionIssue 1:1 dengan order
            /** @var \App\Models\ProductionIssue|null $issue */
            $issue = ProductionIssue::where('order_id', $order->id)->lockForUpdate()->first();

            if (!$issue) {
                // Safety: buat issue bila tidak ada (harusnya ada)
                $issueCounter = ProductionIssue::lockForUpdate()->count() + 1;
                $issueNumber  = 'IS-' . $now->format('Ymd') . '-' . str_pad($issueCounter, 4, '0', STR_PAD_LEFT);

                $issue = ProductionIssue::create([
                    'issue_date'              => $now->toDateString(),
                    'issue_number'            => $issueNumber,
                    'notes'                   => null,
                    'status'                  => 'posted',
                    'posted_at'               => $now,
                    'posted_by'               => Auth::id(),
                    'order_id'                => $order->id,
                    'requested_at'            => $now,
                    'requested_by'            => Auth::id(),
                    'purchase_order_style_id' => $style?->id,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ]);
            }

            // ====== BEFORE: map item lama (by stock_id) ======
            $before = [];
            foreach ($order->items as $it) {
                $before[(int) $it->stock_id] = [
                    'id'    => $it->id,
                    'qty_base' => $this->toBaseQty((float) $it->quantity, $it->unit),
                    'unit'     => $it->unit,
                    'notes'    => (string) ($it->notes ?? ''),
                ];
            }

            // ====== AFTER: map item baru (by stock_id) ======
            $after = [];
            foreach ($data['items'] as $i => $row) {
                $sid  = (int) $row['stock_id'];
                $qty  = (float) $row['quantity'];
                $note = trim((string) ($row['notes'] ?? ''));

                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "items.$i.quantity" => "Qty harus > 0",
                    ]);
                }

                $after[$sid] = [
                    'qty_input' => $qty,
                    'notes'     => $note,
                ];
            }

            // Lock semua stok yang terdampak
            $allStockIds = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
            $stocks = Stock::whereIn('id', $allStockIds)->lockForUpdate()->get()->keyBy('id');

            // Validasi jenis stok sesuai source_type
            foreach ($allStockIds as $sid) {
                /** @var \App\Models\Stock|null $st */
                $st = $stocks[$sid] ?? null;
                if (!$st) {
                    continue;
                }

                if ($sourceType === 'po') {
                    // Harus stok normal & satu PO dengan Style
                    if (!empty($st->buyer_id)) {
                        throw ValidationException::withMessages([
                            'items' => ["Item {$st->material_name} adalah stok FOB (Buyer). Order ini sumbernya stok PO."],
                        ]);
                    }

                    if (!empty($st->purchase_order_id) && $st->purchase_order_id !== $po->id) {
                        throw ValidationException::withMessages([
                            'items' => ["Item {$st->material_name} bukan dari PO {$po->po_number}."],
                        ]);
                    }
                } elseif ($sourceType === 'fob') {
                    // Mode FOB
                    if (empty($st->buyer_id)) {
                        throw ValidationException::withMessages([
                            'items' => ["Item {$st->material_name} bukan stok FOB (Buyer)."],
                        ]);
                    }

                    if ($buyerId && $st->buyer_id !== $buyerId) {
                        throw ValidationException::withMessages([
                            'items' => ["Item {$st->material_name} bukan stok FOB dari Buyer yang sama dengan header Order."],
                        ]);
                    }
                    // purchase_order_id stok FOB boleh beda / null
                } else {
                    // Mode Mixed: boleh gabung stok PO/Global + FOB
                    if (!is_null($st->buyer_id)) {
                        if (!$buyerId || $st->buyer_id !== $buyerId) {
                            throw ValidationException::withMessages([
                                'items' => ["Item {$st->material_name} bukan stok FOB dari Buyer yang sama dengan header Order."],
                            ]);
                        }
                    } else {
                        if (!empty($st->purchase_order_id) && $st->purchase_order_id !== $po->id) {
                            throw ValidationException::withMessages([
                                'items' => ["Item {$st->material_name} bukan dari PO {$po->po_number}."],
                            ]);
                        }

                        if (empty($st->purchase_order_id) && (int) $st->supplier_id !== (int) $po->supplier_id) {
                            throw ValidationException::withMessages([
                                'items' => ["Item {$st->material_name} bukan stok global untuk Buyer yang sama dengan PO."],
                            ]);
                        }
                    }
                }
            }

            // ====== 1) ADD / UPDATE item ======
            foreach ($after as $sid => $newRow) {
                /** @var \App\Models\Stock $st */
                $st = $stocks[$sid] ?? null;
                if (!$st) {
                    throw ValidationException::withMessages(['items' => 'Stok tidak ditemukan.']);
                }

                $displayUnit = $this->displayUnit($st->unit);
                $newQtyBase  = $this->toBaseQty((float) $newRow['qty_input'], $displayUnit);
                $newNote     = $newRow['notes'];

                $supplierIdForDocs     = $st->supplier_id;
                $supplierIdForMovement = (int) ($st->supplier_id ?? 0);

                if (isset($before[$sid])) {
                    // UPDATE item (ada sebelumnya)
                    $old    = $before[$sid];
                    $itemId = (int) $old['id'];
                    $oldQtyBase = (float) $old['qty_base'];
                    $deltaBase  = $newQtyBase - $oldQtyBase;
                    $deltaStock = $this->fromBaseQty($deltaBase, $st->unit);

                    if ($deltaStock > 0) {
                        // butuh stok tambahan
                        $availableBase = $this->toBaseQty((float) $st->quantity, $st->unit);
                        if ($st->quantity < $deltaStock) {
                            throw ValidationException::withMessages([
                                'items' => ["Qty {$st->material_name} melebihi sisa stok (tersedia: {$availableBase})."],
                            ]);
                        }
                        $st->decrement('quantity', $deltaStock);
                    } elseif ($deltaStock < 0) {
                        // kembalikan sisa ke stok
                        $st->increment('quantity', abs($deltaStock));
                    }

                    // Update OrderItem
                    OrderItem::whereKey($itemId)->update([
                        'unit'       => $displayUnit,
                        'quantity'   => $newQtyBase,
                        'notes'      => $newNote ?: null,
                        'updated_at' => $now,
                    ]);

                    // Update ProductionIssueItem
                    ProductionIssueItem::where('order_item_id', $itemId)
                        ->where('production_issue_id', $issue->id)
                        ->update([
                            'unit'       => $displayUnit,
                            'quantity'   => $newQtyBase,
                            'notes'      => $newNote ?: null,
                            'updated_at' => $now,
                        ]);

                    // Update StockMovement OUT
                    $mov = StockMovement::where('order_item_id', $itemId)
                        ->where('direction', StockMovement::DIR_OUT)
                        ->lockForUpdate()
                        ->first();

                    if ($mov) {
                        $mov->update([
                            'unit'       => $displayUnit,
                            'quantity'   => $newQtyBase,
                            'notes'      => $newNote ?: null,
                            'updated_at' => $now,
                        ]);
                    } else {
                        // safety: kalau movement belum ada, buat baru
                        $poNumberForMovement = optional($po)->po_number ?? optional($st->purchaseOrder)->po_number;

                        StockMovement::recordOut(
                            stockId:     $st->id,
                            supplierId:  $supplierIdForMovement,
                            material:    $st->material_name,
                            unit:        $displayUnit,
                            qty:         (float) $newQtyBase,
                            poNumber:    $poNumberForMovement ?: null,
                            notes:       $newNote ?: null,
                            movedAt:     $now,
                            orderId:     $order->id,
                            orderItemId: $itemId,
                        );
                    }
                } else {
                    // ADD item baru
                    $newQtyStock = $this->fromBaseQty($newQtyBase, $st->unit);
                    if ($st->quantity < $newQtyStock) {
                        $availableBase = $this->toBaseQty((float) $st->quantity, $st->unit);
                        throw ValidationException::withMessages([
                            'items' => ["Qty {$st->material_name} melebihi sisa stok (tersedia: {$availableBase})."],
                        ]);
                    }

                    // a) OrderItem baru
                    $orderItem = OrderItem::create([
                        'order_id'       => $order->id,
                        'stock_id'       => $st->id,
                        'supplier_id'    => $supplierIdForDocs,
                        'material_code'  => $st->material_code,
                        'material_name'  => $st->material_name,
                        'unit'           => $displayUnit,
                        'quantity'       => $newQtyBase,
                        'notes'          => $newNote ?: null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);

                    // b) Issue Item baru
                    ProductionIssueItem::create([
                        'production_issue_id' => $issue->id,
                        'order_item_id'       => $orderItem->id,
                        'stock_id'            => $st->id,
                        'supplier_id'         => $supplierIdForDocs,
                        'material_code'       => $st->material_code,
                        'material_name'       => $st->material_name,
                        'unit'                => $displayUnit,
                        'quantity'            => $newQtyBase,
                        'notes'               => $newNote ?: null,
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ]);

                    // c) kurangi stok
                    $st->decrement('quantity', $newQtyStock);

                    // d) Movement OUT baru
                    $poNumberForMovement = optional($po)->po_number ?? optional($st->purchaseOrder)->po_number;

                    StockMovement::recordOut(
                        stockId:     $st->id,
                        supplierId:  $supplierIdForMovement,
                        material:    $st->material_name,
                        unit:        $displayUnit,
                        qty:         (float) $newQtyBase,
                        poNumber:    $poNumberForMovement ?: null,
                        notes:       $newNote ?: null,
                        movedAt:     $now,
                        orderId:     $order->id,
                        orderItemId: $orderItem->id,
                    );
                }
            }

            // ====== 2) REMOVE item yang dihapus ======
            foreach ($before as $sid => $old) {
                if (isset($after[$sid])) {
                    continue; // masih ada
                }

                /** @var \App\Models\Stock|null $st */
                $st = $stocks[$sid] ?? null;
                if (!$st) {
                    continue;
                }

                $itemId = (int) $old['id'];
                $oldQtyBase = (float) $old['qty_base'];
                $oldQtyStock = $this->fromBaseQty($oldQtyBase, $st->unit);

                // kembalikan stok
                $st->increment('quantity', $oldQtyStock);

                // hapus movement OUT
                StockMovement::where('order_item_id', $itemId)
                    ->where('direction', StockMovement::DIR_OUT)
                    ->delete();

                // hapus issue item
                ProductionIssueItem::where('order_item_id', $itemId)
                    ->where('production_issue_id', $issue->id)
                    ->delete();

                // hapus order item
                OrderItem::whereKey($itemId)->delete();
            }
        });

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Permintaan berhasil diperbarui & stok disesuaikan.');
    }

    /**
     * Hapus permintaan + rollback:
     * - Kembalikan stok
     * - Hapus StockMovement OUT terkait
     * - Hapus ProductionIssue & itemnya
     * - Hapus OrderItem & Order
     */
    public function destroy(Order $order)
    {
        try {
            DB::transaction(function () use ($order) {
                $order->load(['items.stock']);

                // 1) Kembalikan stok
                foreach ($order->items as $item) {
                    if ($item->stock) {
                        $qtyBase  = $this->toBaseQty((float) $item->quantity, $item->unit);
                        $qtyStock = $this->fromBaseQty($qtyBase, $item->stock->unit);
                        $item->stock->increment('quantity', $qtyStock);
                    }
                }

                // 2) Hapus movement OUT yang terkait order / itemnya
                $orderItemIds = $order->items->pluck('id')->all();
                StockMovement::where('order_id', $order->id)
                    ->orWhereIn('order_item_id', $orderItemIds)
                    ->delete();

                // 3) Hapus Production Issue + items
                $issues = ProductionIssue::where('order_id', $order->id)->get();
                foreach ($issues as $issue) {
                    $issue->items()->delete();
                    $issue->delete();
                }

                // 4) Hapus item & header order
                $order->items()->delete();
                $order->delete();
            });

            return redirect()
                ->route('admin.orders.index')
                ->with('success', 'Permintaan dibatalkan & stok dikembalikan seperti semula.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('warning', 'Gagal membatalkan permintaan. Silakan coba lagi.');
        }
    }

    public function receiptPdf(Order $order)
    {
        $order->load([
            'user',
            'buyer',
            'items.stock.supplier',
            'items.stock.buyer',
            'items.stock.purchaseOrder',
            'purchaseOrderStyle.purchaseOrder',
        ]);

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
