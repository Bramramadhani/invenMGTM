<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\StockMovement;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Ambil filter dari request
        $filterIn       = (string) $request->input('filter_in', '5');
        $filterOutChart = (string) $request->input('filter_out', '5');       // <- chart OUT
        $filterOutList  = (string) $request->input('filter_list_out', '5');  // <- tabel OUT
        $filterListPo   = (string) $request->input('filter_list_po', '5');   // <- chart/list PO

        // helper limit Top 5/10/All
        $getLimit = function ($filter) {
            if (in_array($filter, ['5','10'], true)) return (int) $filter;
            if ($filter === 'all') return null; // null = tanpa limit
            return 5; // default
        };

        $limitIn       = $getLimit($filterIn);
        $limitOutChart = $getLimit($filterOutChart);
        $limitOutList  = $getLimit($filterOutList);
        $limitListPo   = $getLimit($filterListPo);

        // LIMIT UNTUK PANEL PO PROGRESS (10/20/50/All)
        $poLimitOpt = (string) $request->input('po_limit', '20');
        $poLimit    = in_array($poLimitOpt, ['10','20','50','all'], true) ? $poLimitOpt : '20';

        // KPI kecil
        $suppliers  = Supplier::count();
        $products   = PurchaseOrderItem::distinct('material_name')->count();
        $transactions = (int) PurchaseReceiptItem::sum('received_quantity');

        $transactionThisMonth = (int) PurchaseReceiptItem::join('purchase_receipts', 'purchase_receipt_items.purchase_receipt_id', '=', 'purchase_receipts.id')
            ->whereYear('purchase_receipts.receipt_date', date('Y'))
            ->whereMonth('purchase_receipts.receipt_date', date('m'))
            ->sum('purchase_receipt_items.received_quantity');

        // Opsional
        $productsOutStock = PurchaseOrderItem::select('material_name', DB::raw('SUM(actual_arrived_quantity) as total'))
            ->groupBy('material_name')
            ->having('total', '<=', 10)
            ->get();

        $orders = PurchaseOrder::where('is_completed', 0)->get();

        // ==========================
        // LIST MATERIAL KELUAR — transaksi OUT terbaru (tabel)
        $outListQuery = StockMovement::query()
            ->with([
                'supplier:id,name',
                'stock:id,material_code,material_name,unit',
            ])
            ->where('direction', 'OUT')
            ->orderByDesc('moved_at');

        if ($limitOutList) $outListQuery->limit($limitOutList);

        $outList = $outListQuery->get([
            'id','stock_id','supplier_id','po_number','material_name','unit','quantity','moved_at','notes',
        ]);

        // ==========================
        // PO Progress: ordered vs received (urut NATURAL DESC po_number → terbaru)
        $poSupplierId = $request->input('po_supplier_id');
        $poNumberQ    = $request->input('po_number');

        $poSuppliers = Supplier::orderBy('name')->get(['id','name']);

        $poBaseQuery = PurchaseOrder::with('supplier');
        if ($poSupplierId) $poBaseQuery->where('supplier_id', $poSupplierId);
        if ($poNumberQ)    $poBaseQuery->where('po_number', 'like', '%'.$poNumberQ.'%');

        $allPOs = $poBaseQuery->get(['id','po_number','supplier_id','created_at']);
        $poIds  = $allPOs->pluck('id')->toArray();

        $ordered = !empty($poIds)
            ? PurchaseOrderItem::whereIn('purchase_order_id', $poIds)
                ->select('purchase_order_id', DB::raw('SUM(ordered_quantity) as total_ordered'))
                ->groupBy('purchase_order_id')
                ->pluck('total_ordered', 'purchase_order_id')
                ->toArray()
            : [];

        $receivedByPoId = !empty($poIds)
            ? PurchaseReceiptItem::query()
                ->join('purchase_receipts as pr', 'pr.id', '=', 'purchase_receipt_items.purchase_receipt_id')
                ->join('purchase_order_items as poi', 'poi.id', '=', 'purchase_receipt_items.purchase_order_item_id')
                ->where('pr.status', PurchaseReceipt::STATUS_POSTED)
                ->whereIn('poi.purchase_order_id', $poIds)
                ->selectRaw('poi.purchase_order_id as po_id, SUM(purchase_receipt_items.received_quantity) as qty_in')
                ->groupBy('poi.purchase_order_id')
                ->pluck('qty_in', 'po_id')
                ->toArray()
            : [];

        $poProgress = $allPOs->map(function ($po) use ($ordered, $receivedByPoId) {
            $orderedQty  = (float) ($ordered[$po->id] ?? 0);
            $receivedQty = (float) ($receivedByPoId[$po->id] ?? 0);
            $pct         = $orderedQty > 0 ? round(($receivedQty / $orderedQty) * 100, 2) : 0;

            $status = 'Pending';
            if ($receivedQty > 0 && $receivedQty < $orderedQty) $status = 'Partial';
            if ($receivedQty >= $orderedQty && $orderedQty > 0) $status = 'Complete';
            if ($receivedQty >  $orderedQty)                    $status = 'Over';

            return [
                'id'          => $po->id,
                'number'      => $po->po_number,
                'supplier_id' => $po->supplier_id,
                'supplier'    => optional($po->supplier)->name ?? '-',
                'ordered'     => $orderedQty,
                'received'    => $receivedQty,
                'pct'         => $pct,
                'status'      => $status,
            ];
        })->all();

        // urut NATURAL DESC (terbaru -> terlama) berdasarkan po_number
        usort($poProgress, function ($a, $b) {
            return strnatcasecmp($b['number'] ?? '', $a['number'] ?? '');
        });

        // apply limit 10/20/50 bila bukan 'all'
        if ($poLimit !== 'all') {
            $poProgress = array_slice($poProgress, 0, (int) $poLimit);
        }

        // ==========================
        // (tetap) daftar per-kedatangan (list sederhana)
        $poQueryReceipts = PurchaseReceipt::with(['purchaseOrder.supplier'])
            ->where('status', PurchaseReceipt::STATUS_POSTED)
            ->orderByDesc('receipt_date');

        if ($limitListPo) $poQueryReceipts->limit($limitListPo);
        $recentReceipts = $poQueryReceipts->get();

        $receiptIds = $recentReceipts->pluck('id')->toArray();

        $items = [];
        if (!empty($receiptIds)) {
            $items = PurchaseReceiptItem::whereIn('purchase_receipt_id', $receiptIds)
                ->select('purchase_receipt_id', 'material_name', DB::raw('SUM(received_quantity) as total'))
                ->groupBy('purchase_receipt_id', 'material_name')
                ->get();
        }

        $charts = [];
        foreach ($recentReceipts as $r) {
            $rows   = $items ? $items->where('purchase_receipt_id', $r->id) : collect();
            $labels = $rows->pluck('material_name')->toArray();
            $series = $rows->pluck('total')->map(fn($v) => (int) $v)->toArray();

            $poNumber     = optional($r->purchaseOrder)->po_number;
            $supplierName = optional(optional($r->purchaseOrder)->supplier)->name;

            $title = $poNumber ? ("PO: {$poNumber}") : ("Receipt #{$r->id}");
            if ($supplierName) $title .= ' — ' . $supplierName;
            $title .= ' — ' . optional($r->receipt_date)->format('d-m-Y');

            if (!empty($series)) {
                $charts[] = [
                    'id'     => $r->id,
                    'title'  => $title,
                    'labels' => $labels,
                    'series' => $series,
                ];
            }
        }

        // ==========================
        // CHART BATANG — Total Kedatangan per PO (urut NATURAL DESC po_number)
        $rows = PurchaseReceiptItem::query()
            ->join('purchase_receipts as pr', 'pr.id', '=', 'purchase_receipt_items.purchase_receipt_id')
            ->join('purchase_orders as po', 'po.id', '=', 'pr.purchase_order_id')
            ->where('pr.status', PurchaseReceipt::STATUS_POSTED)
            ->select('po.id as po_id', 'po.po_number', 'po.supplier_id', DB::raw('SUM(purchase_receipt_items.received_quantity) as total'))
            ->groupBy('po.id','po.po_number','po.supplier_id')
            ->get();

        // urut NATURAL DESC berdasarkan nomor PO (terbaru -> terlama)
        $rows = $rows->sort(function ($a, $b) {
            return strnatcasecmp($b->po_number ?? '', $a->po_number ?? '');
        })->values();

        // terapkan Top N (5/10/All) untuk chart PO
        if ($limitListPo) {
            $rows = $rows->take($limitListPo);
        }

        $supplierMap = Supplier::whereIn('id', $rows->pluck('supplier_id')->unique())
            ->pluck('name','id');

        $poChart = null;
        if ($rows->isNotEmpty()) {
            $labels = $rows->map(function ($r) use ($supplierMap) {
                $poLabel = $r->po_number ?: ('#'.$r->po_id);
                $supp    = $supplierMap[$r->supplier_id] ?? '-';
                return $poLabel . ' — ' . $supp;
            })->all();

            $series = $rows->pluck('total')->map(fn($v) => (int)$v)->all();

            $poChart = [
                'title'  => 'Total Kedatangan per PO (Posted Receipts)',
                'labels' => $labels,
                'series' => $series,
            ];
        }

        // ==========================
        // NEW: outChart — Chart Material Keluar (Top 5/10/All) by material_name + unit
        $outAggQ = StockMovement::query()
            ->where('direction', 'OUT')
            ->select('material_name','unit', DB::raw('SUM(quantity) as total'))
            ->groupBy('material_name','unit')
            ->orderByDesc('total');

        if ($limitOutChart) {
            $outAggQ->limit($limitOutChart);
        }

        $outAgg = $outAggQ->get();

        $outChart = null;
        if ($outAgg->isNotEmpty()) {
            $outChart = [
                'title'  => 'Top Material Keluar',
                'labels' => $outAgg->map(fn($r) => trim(($r->material_name ?: '—') . ' — ' . ($r->unit ?: '')))->values()->all(),
                'series' => $outAgg->pluck('total')->map(fn($v) => (float)$v)->values()->all(),
            ];
        }

        return view('admin.dashboard', compact(
            'suppliers',
            'products',
            'transactions',
            'transactionThisMonth',
            'productsOutStock',
            'orders',
            'outList',
            'poProgress',
            'poSuppliers',
            'poSupplierId',
            'poNumberQ',
            'poLimit',
            'filterIn',
            'filterOutChart',
            'filterOutList',
            'filterListPo',
            'charts',
            'poChart',
            'outChart'   // NEW: kirim ke blade
        ));
    }
}
