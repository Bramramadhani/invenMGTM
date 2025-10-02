<?php

namespace App\Http\Controllers\Admin;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceiptItem;
use App\Models\StockMovement;
use App\Models\PurchaseReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Ambil filter dari request
        $filterIn       = (string) $request->input('filter_in', '5');
        $filterOutChart = (string) $request->input('filter_out', '5');
        $filterOutList  = (string) $request->input('filter_list_out', '5');
        $filterListPo   = (string) $request->input('filter_list_po', '5');

        // fungsi kecil untuk validasi filter
        $getLimit = function ($filter) {
            if (in_array($filter, ['5','10'], true)) {
                return (int) $filter;
            } elseif ($filter === 'all') {
                return null; // null artinya tidak ada limit
            }
            return 5; // fallback default
        };

        $limitIn       = $getLimit($filterIn);
        $limitOutChart = $getLimit($filterOutChart);
        $limitOutList  = $getLimit($filterOutList);
        $limitListPo   = $getLimit($filterListPo);

        // Jumlah Supplier
        $suppliers = Supplier::count();

        // Total produk unik
        $products = PurchaseOrderItem::distinct('material_name')->count();

        // Total barang masuk
        $transactions = (int) PurchaseReceiptItem::sum('received_quantity');

        // Barang masuk bulan ini
        $transactionThisMonth = (int) PurchaseReceiptItem::join('purchase_receipts', 'purchase_receipt_items.purchase_receipt_id', '=', 'purchase_receipts.id')
            ->whereYear('purchase_receipts.receipt_date', date('Y'))
            ->whereMonth('purchase_receipts.receipt_date', date('m'))
            ->sum('purchase_receipt_items.received_quantity');

        // Produk stok <= 10
        $productsOutStock = PurchaseOrderItem::select('material_name', DB::raw('SUM(actual_arrived_quantity) as total'))
            ->groupBy('material_name')
            ->having('total', '<=', 10)
            ->get();

        // PO yang belum selesai
        $orders = PurchaseOrder::where('is_completed', 0)->get();

        // -------------------------
        // Chart Material Masuk
        $inQuery = PurchaseReceiptItem::select('material_name', DB::raw('SUM(received_quantity) as total'))
            ->groupBy('material_name')
            ->orderByDesc('total');

        $inProductsForChart = $limitIn ? (clone $inQuery)->limit($limitIn)->get() : $inQuery->get();
        $inLabel = $inProductsForChart->pluck('material_name')->toArray();
        $inTotal = $inProductsForChart->pluck('total')->map(fn($v) => (int)$v)->toArray();

        // -------------------------
        // Chart Material Keluar
        $outQuery = StockMovement::where('direction', StockMovement::DIR_OUT)
            ->select('material_name', DB::raw('SUM(quantity) as total'))
            ->groupBy('material_name')
            ->orderByDesc('total');

        $outMovesForChart = $limitOutChart ? (clone $outQuery)->limit($limitOutChart)->get() : $outQuery->get();
        $outLabel = $outMovesForChart->pluck('material_name')->toArray();
        $outTotal = $outMovesForChart->pluck('total')->map(fn($v) => (int)$v)->toArray();

        // -------------------------
        // List Material Keluar
        $outListQuery = clone $outQuery;
        if ($limitOutList) {
            $outListQuery->limit($limitOutList);
        }
        $outList = $outListQuery->get()->map(fn($m) => [
            'material_name' => $m->material_name,
            'quantity' => (int)$m->total
        ])->values()->toArray();

        // -------------------------
        // List per kedatangan PO
        $poQuery = PurchaseReceipt::with(['purchaseOrder.supplier'])
            ->where('status', PurchaseReceipt::STATUS_POSTED)
            ->orderByDesc('receipt_date');

        if ($limitListPo) {
            $poQuery->limit($limitListPo);
        }
        $recentReceipts = $poQuery->get();

        $receiptIds = $recentReceipts->pluck('id')->toArray();
        $items = PurchaseReceiptItem::whereIn('purchase_receipt_id', $receiptIds)
            ->select('purchase_receipt_id', 'material_name', DB::raw('SUM(received_quantity) as total'))
            ->groupBy('purchase_receipt_id', 'material_name')
            ->get();

        $charts = [];
        foreach ($recentReceipts as $r) {
            $rows = $items->where('purchase_receipt_id', $r->id);
            $labels = $rows->pluck('material_name')->toArray();
            $series = $rows->pluck('total')->map(fn($v) => (int)$v)->toArray();
            $poNumber = optional($r->purchaseOrder)->po_number;
            $supplierName = optional(optional($r->purchaseOrder)->supplier)->name;
            $title = $poNumber ? ("PO: {$poNumber}") : ("Receipt #{$r->id}");
            if ($supplierName) $title .= ' — '. $supplierName;
            $title .= ' — '.optional($r->receipt_date)->format('d-m-Y');

            if (!empty($series)) {
                $charts[] = [
                    'id' => $r->id,
                    'title' => $title,
                    'labels' => $labels,
                    'series' => $series,
                ];
            }
        }

        return view('admin.dashboard', compact(
            'suppliers',
            'products',
            'transactions',
            'transactionThisMonth',
            'productsOutStock',
            'orders',
            'outLabel',
            'outTotal',
            'inLabel',
            'inTotal',
            'charts',
            'outList',
            'filterIn',
            'filterOutChart',
            'filterOutList',
            'filterListPo'
        ));
    }
}
