<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

// pastikan nama & namespace ini cocok dengan file-mu:
// app/Reports/Exports/StockMovementsExport.php
use App\Reports\Exports\StockMovementsExport;

class ReportController extends Controller
{
    /**
     * Halaman laporan: filter + KPI + tabel IN/OUT
     */
    public function index(Request $request)
    {
        // ====== FILTER ====== 
        $dateFrom    = $request->input('date_from');
        $dateTo      = $request->input('date_to');
        $supplierId  = $request->input('supplier_id');
        $q           = trim((string) $request->input('q', ''));
        $type        = $request->input('type', 'all'); // all | in | out
        $showNames   = (bool) $request->boolean('show_names'); // toggle tampilkan 3 nama

        // Default: bulan berjalan
        if (!$dateFrom || !$dateTo) {
            $start    = Carbon::now()->startOfMonth();
            $end      = Carbon::now()->endOfMonth();
            $dateFrom = $dateFrom ?: $start->toDateString();
            $dateTo   = $dateTo   ?: $end->toDateString();
        }

        // Query dasar movements (ikut eager-load supplier/stock + order/orderItem->order)
        $base = StockMovement::query()
            ->withReportRelations()
            ->whereDate('moved_at', '>=', $dateFrom)
            ->whereDate('moved_at', '<=', $dateTo);

        if ($supplierId) {
            $base->where('supplier_id', $supplierId);
        }

        if ($q !== '') {
            $base->search($q);
        }

        // Clone untuk IN/OUT
        $qIn  = (clone $base)->where('direction', StockMovement::DIR_IN);
        $qOut = (clone $base)->where('direction', StockMovement::DIR_OUT);

        // KPI
        $totalInQty  = (float) $qIn->sum('quantity');
        $totalOutQty = (float) $qOut->sum('quantity');
        
        // Debug: Log detail transaksi untuk membantu investigasi
        Log::info('Report KPI Debug', [
            'date_range' => "$dateFrom to $dateTo",
            'supplier_id' => $supplierId,
            'in_transactions' => $qIn->select(['id', 'moved_at', 'quantity', 'direction', 'notes'])->get(),
            'out_transactions' => $qOut->select(['id', 'moved_at', 'quantity', 'direction', 'notes'])->get(),
            'total_in' => $totalInQty,
            'total_out' => $totalOutQty
        ]);

        $netQty = $totalInQty - $totalOutQty;

        // Data tabel (limit agar ringan; bisa dipaginate jika perlu)
        $inRows  = $type !== 'out'
            ? (clone $base)->where('direction', StockMovement::DIR_IN)
                ->orderBy('moved_at', 'desc')->orderBy('id','desc')->limit(1000)->get()
            : collect();

        $outRows = $type !== 'in'
            ? (clone $base)->where('direction', StockMovement::DIR_OUT)
                ->orderBy('moved_at', 'desc')->orderBy('id','desc')->limit(1000)->get()
            : collect();

        // Get all suppliers ordered by name
        $suppliers = Supplier::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return view('admin.reports.index', [
            'filters'     => [
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
                'supplier_id' => $supplierId,
                'q'           => $q,
                'type'        => $type,
            ],
            'suppliers'   => $suppliers,
            'totalInQty'  => $totalInQty,
            'totalOutQty' => $totalOutQty,
            'netQty'      => $netQty,
            'inRows'      => $inRows,
            'outRows'     => $outRows,
        ]);
    }

    /**
     * Export Excel dengan format rapi (dibuat oleh StockMovementsExport)
     * Mengikuti filter halaman.
     */
    public function export(Request $request)
    {
        $dateFrom    = $request->input('date_from');
        $dateTo      = $request->input('date_to');
        $supplierId  = $request->input('supplier_id');
        $q           = trim((string) $request->input('q', ''));
        $type        = $request->input('type', 'all'); // all|in|out
        $showNames   = (bool) $request->boolean('show_names');

        if (!$dateFrom || !$dateTo) {
            $start    = Carbon::now()->startOfMonth();
            $end      = Carbon::now()->endOfMonth();
            $dateFrom = $dateFrom ?: $start->toDateString();
            $dateTo   = $dateTo   ?: $end->toDateString();
        }

        // ❗ Tidak perlu build $rows di controller; biarkan export class yang ambil data

        // Simpel: gunakan nama file statis 'report.xlsx'
        $filename = 'report.xlsx';

        // Pass same filters to the export class
        $export = new StockMovementsExport($dateFrom, $dateTo, $supplierId, $q, $type, $showNames);

        return Excel::download($export, $filename);
    }
}
