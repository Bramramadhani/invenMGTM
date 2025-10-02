<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

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

        // Default: bulan berjalan
        if (!$dateFrom || !$dateTo) {
            $start = Carbon::now()->startOfMonth();
            $end   = Carbon::now()->endOfMonth();
            $dateFrom = $dateFrom ?: $start->toDateString();
            $dateTo   = $dateTo   ?: $end->toDateString();
        }

        // Siapkan query dasar movements
        $base = StockMovement::query()
            ->with(['supplier:id,name', 'stock:id,material_code,material_name,unit,last_po_number'])
            ->whereDate('moved_at', '>=', $dateFrom)
            ->whereDate('moved_at', '<=', $dateTo);

        if ($supplierId) {
            $base->where('supplier_id', $supplierId);
        }

        if ($q !== '') {
            $like = "%{$q}%";
            $base->where(function ($w) use ($like) {
                $w->where('material_name', 'like', $like)
                  ->orWhere('unit', 'like', $like)
                  ->orWhere('po_number', 'like', $like)
                  ->orWhere('notes', 'like', $like)
                  ->orWhereHas('stock', function ($s) use ($like) {
                      $s->where('material_code', 'like', $like);
                  })
                  ->orWhereHas('supplier', function ($s) use ($like) {
                      $s->where('name', 'like', $like);
                  });
            });
        }

        // Clone untuk IN/OUT
        $qIn  = (clone $base)->where('direction', StockMovement::DIR_IN);
        $qOut = (clone $base)->where('direction', StockMovement::DIR_OUT);

        // KPI
        $totalInQty  = (float) $qIn->sum('quantity');
        $totalOutQty = (float) $qOut->sum('quantity');
        $netQty      = $totalInQty - $totalOutQty;

        // Data tabel (batasi supaya tidak terlalu berat; bisa dibuat paginate jika perlu)
        $inRows  = $type !== 'out'
            ? (clone $base)->where('direction', StockMovement::DIR_IN)
                ->orderBy('moved_at', 'desc')->orderBy('id','desc')->limit(1000)->get()
            : collect();

        $outRows = $type !== 'in'
            ? (clone $base)->where('direction', StockMovement::DIR_OUT)
                ->orderBy('moved_at', 'desc')->orderBy('id','desc')->limit(1000)->get()
            : collect();

        $suppliers = Supplier::orderBy('name')->get(['id','name']);

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
     * Export CSV (mengikuti filter di halaman)
     * ?type=all|in|out
     */
    public function export(Request $request)
    {
        $dateFrom    = $request->input('date_from');
        $dateTo      = $request->input('date_to');
        $supplierId  = $request->input('supplier_id');
        $q           = trim((string) $request->input('q', ''));
        $type        = $request->input('type', 'all'); // all|in|out

        if (!$dateFrom || !$dateTo) {
            $start = Carbon::now()->startOfMonth();
            $end   = Carbon::now()->endOfMonth();
            $dateFrom = $dateFrom ?: $start->toDateString();
            $dateTo   = $dateTo   ?: $end->toDateString();
        }

        $base = StockMovement::query()
            ->with(['supplier:id,name', 'stock:id,material_code,material_name,unit,last_po_number'])
            ->whereDate('moved_at', '>=', $dateFrom)
            ->whereDate('moved_at', '<=', $dateTo);

        if ($supplierId) {
            $base->where('supplier_id', $supplierId);
        }
        if ($q !== '') {
            $like = "%{$q}%";
                        $base->where(function ($w) use ($like) {
                                $w->where('material_name', 'like', $like)
                                    ->orWhere('unit', 'like', $like)
                                    ->orWhere('po_number', 'like', $like)
                                    ->orWhere('notes', 'like', $like)
                                    ->orWhereHas('stock', fn($s) => $s->where('material_code', 'like', $like))
                                    ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', $like));
                        });
        }

        if ($type === 'in')  $base->where('direction', StockMovement::DIR_IN);
        if ($type === 'out') $base->where('direction', StockMovement::DIR_OUT);

        $rows = $base->orderBy('moved_at','desc')->orderBy('id','desc')->get();

        $filename = 'report_movements_'.($type).'_'.$dateFrom.'_to_'.$dateTo.'.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            // Header CSV
            fputcsv($out, [
                'Tanggal',
                'Jenis',       // IN/OUT
                'Supplier',
                'No PO',
                'Kode',
                'Material',
                'Unit',
                'Qty',
                'Catatan',
            ]);

            foreach ($rows as $r) {
                $supplier = optional($r->supplier)->name ?? '';
                $code     = optional($r->stock)->material_code ?? '';
                $unit     = $r->unit ?? (optional($r->stock)->unit ?? '');
                $material = $r->material_name ?? optional($r->stock)->material_name ?? $r->material;
                fputcsv($out, [
                    optional($r->moved_at)->format('Y-m-d H:i:s'),
                    $r->direction === StockMovement::DIR_IN ? 'IN' : 'OUT',
                    $supplier,
                    $r->po_number,
                    $code,
                    $material,
                    $unit,
                    $r->quantity,
                    $r->notes,
                ]);
            }

            fclose($out);
        };

        return Response::stream($callback, 200, $headers);
    }
}
