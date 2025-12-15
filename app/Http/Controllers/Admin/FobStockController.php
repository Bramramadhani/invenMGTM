<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockMovement;
use App\Reports\Exports\FobPurchaseReportExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class FobStockController extends Controller
{
    /**
     * Daftar stok FOB
     */
    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q'));
        $buyerId = $request->get('buyer_id');

        $buyers = Buyer::orderBy('name')->get();

        $stocks = Stock::with(['buyer'])
            ->whereNotNull('buyer_id')
            ->when($buyerId, function ($qq) use ($buyerId) {
                $qq->where('buyer_id', $buyerId);
            })
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('material_name', 'like', "%{$q}%")
                      ->orWhere('material_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('material_name')
            ->paginate(20);

        return view('admin.fob_stocks.index', compact('stocks', 'buyers', 'q', 'buyerId'));
    }

    /**
     * Form tambah stok FOB (pembelian FOB)
     */
    public function create()
    {
        $buyers = Buyer::orderBy('name')->get();

        return view('admin.fob_stocks.create', compact('buyers'));
    }

    /**
     * Simpan stok FOB baru (anggap sebagai PEMBELIAN FOB)
     *
     * - buyer_id    : wajib
     * - vendor_name : opsional (toko/vendor tempat beli)
     * - quantity    : qty stok masuk
     * - unit_price  : harga satuan
     * - total harga : disimpan di stock_histories (unit_price * qty)
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate(
            [
                'buyer_id'      => ['required', 'exists:buyers,id'],
                'vendor_name'   => ['nullable', 'string', 'max:191'],
                'material_code' => ['nullable', 'string', 'max:50'],
                'material_name' => ['required', 'string', 'max:255'],
                'unit'          => ['required', 'string', 'max:20'],
                'quantity'      => ['required', 'numeric', 'min:0.0001'],
                'unit_price'    => ['required', 'numeric', 'min:0'],
                'reason'        => ['nullable', 'string', 'max:255'],
            ],
            [],
            [
                'buyer_id'      => 'Buyer',
                'vendor_name'   => 'Vendor / Toko',
                'material_name' => 'Nama Material',
                'unit'          => 'Unit',
                'quantity'      => 'Qty',
                'unit_price'    => 'Harga Satuan',
            ]
        );

        DB::transaction(function () use ($validatedData) {
            $qty       = (float) $validatedData['quantity'];
            $unitPrice = (float) $validatedData['unit_price'];
            $userId    = auth()->id();
            $reason    = $validatedData['reason'] ?? null;

            // 1. Buat stok FOB baru
            $stock = Stock::create([
                'supplier_id'       => null, // stok FOB: biasanya tidak direct ke PO
                'buyer_id'          => $validatedData['buyer_id'],
                'purchase_order_id' => null,
                'material_code'     => $validatedData['material_code'] ?? null,
                'material_name'     => $validatedData['material_name'],
                'unit'              => $validatedData['unit'],
                'quantity'          => $qty,
                'vendor_name'       => $validatedData['vendor_name'] ?? null,
            ]);

            // 2. Catat history dengan harga (ini yang akan dipakai laporan pembelian)
            $totalPrice = $unitPrice * $qty;

            StockHistory::recordChange(
                $stock,
                0,
                $qty,
                StockHistory::TYPE_FOB_CREATE,
                $reason,
                $userId,
                $unitPrice,
                $totalPrice
            );

            // 3. Movement IN (stok bertambah) – tidak perlu harga di sini
            StockMovement::create([
                'stock_id'      => $stock->id,
                'supplier_id'   => $stock->supplier_id,
                'material_name' => $stock->material_name,
                'unit'          => $stock->unit,
                'direction'     => StockMovement::DIR_IN,
                'quantity'      => $qty,
                'notes'         => 'Tambah stok FOB: ' . $stock->material_name,
                'po_number'     => null,
                'moved_at'      => now(),
            ]);
        });

        return redirect()
            ->route('admin.fob-stocks.index')
            ->with('success', 'Pembelian FOB berhasil dicatat & stok bertambah.');
    }

    /**
     * Edit data stok FOB (lebih ke koreksi stok, bukan pembelian baru)
     */
    public function edit(Stock $fob_stock)
    {
        abort_unless($fob_stock->buyer_id, 404);

        $buyers = Buyer::orderBy('name')->get();

        // Ambil harga satuan awal (history TYPE_FOB_CREATE untuk stok ini)
        $initialUnitPrice = StockHistory::where('stock_id', $fob_stock->id)
            ->where('type', StockHistory::TYPE_FOB_CREATE)
            ->orderBy('id')
            ->value('unit_price');

        // KIRIM ke view sebagai $stock (bukan $fob_stock)
        return view('admin.fob_stocks.edit', [
            'stock'            => $fob_stock,
            'buyers'           => $buyers,
            'initialUnitPrice' => $initialUnitPrice,
        ]);
    }

    /**
     * Update stok FOB (koreksi stok).
     *
     * Logika lama tetap dipakai untuk qty (delta dicatat sebagai koreksi),
     * dan jika user mengisi unit_price, harga pembelian awal (TYPE_FOB_CREATE)
     * ikut dikoreksi supaya laporan pembelian FOB memakai harga terbaru.
     */
    public function update(Request $request, Stock $fob_stock)
    {
        abort_unless($fob_stock->buyer_id, 404);

        $validatedData = $request->validate(
            [
                'buyer_id'      => ['required', 'exists:buyers,id'],
                'vendor_name'   => ['nullable', 'string', 'max:191'],
                'material_code' => ['nullable', 'string', 'max:50'],
                'material_name' => ['required', 'string', 'max:255'],
                'unit'          => ['required', 'string', 'max:20'],
                'quantity'      => ['required', 'numeric', 'min:0.0001'],
                'unit_price'    => ['nullable', 'numeric', 'min:0'],
                'reason'        => ['nullable', 'string', 'max:255'],
            ],
            [],
            [
                'buyer_id'      => 'Buyer',
                'vendor_name'   => 'Vendor / Toko',
                'material_name' => 'Nama Material',
                'unit'          => 'Unit',
                'quantity'      => 'Qty',
                'unit_price'    => 'Harga Satuan',
            ]
        );

        DB::transaction(function () use ($validatedData, $fob_stock) {
            $beforeQty = (float) $fob_stock->quantity;
            $afterQty  = (float) $validatedData['quantity'];
            $delta     = $afterQty - $beforeQty;
            $reason    = $validatedData['reason'] ?? null;

            $unitPrice = array_key_exists('unit_price', $validatedData) && $validatedData['unit_price'] !== null
                ? (float) $validatedData['unit_price']
                : null;

            // Update data stok
            $fob_stock->update([
                'buyer_id'      => $validatedData['buyer_id'],
                'vendor_name'   => $validatedData['vendor_name'] ?? null,
                'material_code' => $validatedData['material_code'] ?? null,
                'material_name' => $validatedData['material_name'],
                'unit'          => $validatedData['unit'],
                'quantity'      => $afterQty,
            ]);

            // History koreksi stok FOB (logika lama tetap: fokus ke qty)
            StockHistory::recordChange(
                $fob_stock,
                $beforeQty,
                $afterQty,
                StockHistory::TYPE_FOB_UPDATE,
                $reason,
                auth()->id(),
                $unitPrice,
                $unitPrice !== null ? $unitPrice * $delta : null
            );

            // Movement untuk delta koreksi (tanpa mengubah logika lama)
            if (abs($delta) > 0.0000001) {
                StockMovement::create([
                    'stock_id'      => $fob_stock->id,
                    'supplier_id'   => $fob_stock->supplier_id,
                    'material_name' => $fob_stock->material_name,
                    'unit'          => $fob_stock->unit,
                    'direction'     => $delta > 0
                        ? StockMovement::DIR_IN
                        : StockMovement::DIR_OUT,
                    'quantity'      => abs($delta),
                    'notes'         => 'Koreksi FOB: ' . $reason,
                    'po_number'     => null,
                    'moved_at'      => now(),
                ]);
            }

            // Jika user isi harga baru, koreksi harga di history pembelian awal (TYPE_FOB_CREATE)
            if ($unitPrice !== null) {
                StockHistory::where('stock_id', $fob_stock->id)
                    ->where('type', StockHistory::TYPE_FOB_CREATE)
                    ->orderBy('id')
                    ->limit(1)
                    ->update(['unit_price' => $unitPrice]);
            }
        });

        return redirect()
            ->route('admin.fob-stocks.history', $fob_stock->id)
            ->with('success', 'Stok FOB berhasil diperbarui.');
    }

    /**
     * Tampilkan halaman history/detail untuk 1 stok FOB
     */
    public function history(Stock $fob_stock)
    {
        // Pastikan ini stok FOB
        abort_unless($fob_stock->buyer_id, 404);

        // Load buyer dan histories
        $fob_stock->load('buyer');

        $histories = $fob_stock->histories()
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.fob_stocks.history', [
            'stock'     => $fob_stock,
            'histories' => $histories,
        ]);
    }

    /**
     * Hapus stok FOB (return / koreksi ke 0)
     */
    public function destroy(Stock $fob_stock)
    {
        abort_unless($fob_stock->buyer_id, 404);

        $reason = 'Hapus stok FOB';

        DB::transaction(function () use ($fob_stock, $reason) {
            $beforeQty = (float) $fob_stock->quantity;

            StockHistory::recordChange(
                $fob_stock,
                $beforeQty,
                0,
                StockHistory::TYPE_FOB_DELETE,
                $reason,
                auth()->id(),
            );

            if ($beforeQty > 0) {
                StockMovement::create([
                    'stock_id'      => $fob_stock->id,
                    'supplier_id'   => $fob_stock->supplier_id,
                    'material_name' => $fob_stock->material_name,
                    'unit'          => $fob_stock->unit,
                    'direction'     => StockMovement::DIR_OUT,
                    'quantity'      => $beforeQty,
                    'notes'         => $reason,
                    'po_number'     => null,
                    'moved_at'      => now(),
                ]);
            }

            $fob_stock->delete();
        });

        return redirect()
            ->route('admin.fob-stocks.index')
            ->with('success', 'Stok FOB berhasil dihapus.');
    }

    /**
     * LAPORAN PEMBELIAN FOB
     * - Bisa per TANGGAL (range_type = day)
     * - Bisa per BULAN   (range_type = month)
     */
    public function purchaseReport(Request $request)
    {
        $rangeType = $request->get('range_type', 'day');
        $rangeType = $rangeType === 'month' ? 'month' : 'day';

        $date  = null;
        $month = null;

        if ($rangeType === 'month') {
            // Mode BULANAN
            $monthInput = $request->get('month');

            try {
                $monthCarbon = $monthInput
                    ? Carbon::createFromFormat('Y-m', $monthInput)
                    : Carbon::today();
            } catch (\Exception $e) {
                $monthCarbon = Carbon::today();
            }

            $month = $monthCarbon->format('Y-m');
            $start = $monthCarbon->copy()->startOfMonth();
            $end   = $monthCarbon->copy()->endOfMonth();

            $histories = StockHistory::with(['stock.buyer'])
                ->where('type', StockHistory::TYPE_FOB_CREATE)
                ->whereNotNull('unit_price')
                ->where('diff_quantity', '>', 0)
                ->whereBetween('created_at', [$start, $end])
                ->orderBy('created_at')
                ->get();
        } else {
            // Mode HARIAN
            $dateInput = $request->get('date');

            try {
                $dateCarbon = $dateInput
                    ? Carbon::parse($dateInput)
                    : Carbon::today();
            } catch (\Exception $e) {
                $dateCarbon = Carbon::today();
            }

            $date = $dateCarbon->toDateString();

            $histories = StockHistory::with(['stock.buyer'])
                ->where('type', StockHistory::TYPE_FOB_CREATE)
                ->whereNotNull('unit_price')
                ->where('diff_quantity', '>', 0)
                ->whereDate('created_at', $date)
                ->orderBy('created_at')
                ->get();
        }

        $totalAmount = $histories->sum(function (StockHistory $h) {
            $qty   = (float) $h->diff_quantity;
            $price = (float) ($h->unit_price ?? 0);
            return $qty * $price;
        });

        return view('admin.fob_stocks.report', [
            'rangeType'   => $rangeType,
            'date'        => $date,
            'month'       => $month,
            'histories'   => $histories,
            'totalAmount' => $totalAmount,
        ]);
    }

    /**
     * EXPORT LAPORAN ke Excel (.xlsx) via maatwebsite/excel
     * Menggunakan filter yang sama dengan layar (range_type + date/month)
     */
    public function exportPurchaseReport(Request $request)
    {
        $rangeType = $request->get('range_type', 'day');
        $rangeType = $rangeType === 'month' ? 'month' : 'day';

        $date  = $request->get('date');
        $month = $request->get('month');

        // Fallback supaya nggak null
        if ($rangeType === 'month') {
            if (!$month) {
                if ($date) {
                    try {
                        $month = Carbon::parse($date)->format('Y-m');
                    } catch (\Exception $e) {
                        $month = Carbon::today()->format('Y-m');
                    }
                } else {
                    $month = Carbon::today()->format('Y-m');
                }
            }
        } else {
            if (!$date) {
                $date = Carbon::today()->toDateString();
            }
        }

        // Export class sendiri yang query StockHistory di dalamnya
        $export = new FobPurchaseReportExport($rangeType, $date, $month);

        if ($rangeType === 'month') {
            $label    = $month ?: Carbon::today()->format('Y-m');
            $fileName = 'laporan-pembelian-fob-bulan-' . $label . '.xlsx';
        } else {
            $label    = $date ?: Carbon::today()->toDateString();
            $fileName = 'laporan-pembelian-fob-tanggal-' . $label . '.xlsx';
        }

        return Excel::download($export, $fileName);
    }
}
