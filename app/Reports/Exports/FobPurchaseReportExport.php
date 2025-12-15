<?php

namespace App\Reports\Exports;

use App\Models\StockHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FobPurchaseReportExport implements
    FromCollection,
    ShouldAutoSize,
    WithStyles,
    WithColumnFormatting,
    WithTitle,
    WithEvents
{
    protected string $rangeType; // 'day' atau 'month'
    protected ?string $date;
    protected ?string $month;

    public function __construct(string $rangeType = 'day', ?string $date = null, ?string $month = null)
    {
        $this->rangeType = $rangeType === 'month' ? 'month' : 'day';
        $this->date      = $date;
        $this->month     = $month;
    }

    /**
     * Row:
     *  1 : Judul
     *  2 : Periode
     *  3 : Kosong
     *  4 : Header kolom
     *  5..N : Data
     *  N+1 : (opsional) Total pembelian
     */
    public function collection(): Collection
    {
        // ==== Hitung range & label periode ====
        if ($this->rangeType === 'month') {
            try {
                $monthCarbon = $this->month
                    ? Carbon::createFromFormat('Y-m', $this->month)
                    : Carbon::today();
            } catch (\Exception $e) {
                $monthCarbon = Carbon::today();
            }

            $start       = $monthCarbon->copy()->startOfMonth();
            $end         = $monthCarbon->copy()->endOfMonth();
            $periodLabel = 'Bulan ' . $monthCarbon->format('m-Y');
        } else {
            try {
                $dateCarbon = $this->date
                    ? Carbon::parse($this->date)
                    : Carbon::today();
            } catch (\Exception $e) {
                $dateCarbon = Carbon::today();
            }

            $start       = $dateCarbon->copy()->startOfDay();
            $end         = $dateCarbon->copy()->endOfDay();
            $periodLabel = 'Tanggal ' . $dateCarbon->format('d-m-Y');
        }

        // ==== Query data ====
        $histories = StockHistory::with(['stock.buyer'])
            ->where('type', StockHistory::TYPE_FOB_CREATE)
            ->whereNotNull('unit_price')
            ->where('diff_quantity', '>', 0)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get();

        $rows       = [];
        $grandTotal = 0;

        // ==== Row 1-3: judul & periode ====
        $rows[] = ['LAPORAN PEMBELIAN STOK FOB'];       // A1
        $rows[] = ['Periode: ' . $periodLabel];         // A2
        $rows[] = [];                                   // A3 kosong

        // ==== Row 4: header kolom ====
        $rows[] = [
            'No',             // A
            'Tanggal',        // B
            'Buyer FOB',      // C
            'Vendor / Toko',  // D
            'Kode',           // E
            'Material',       // F
            'Unit',           // G
            'Qty',            // H
            'Harga Satuan',   // I
            'Total',          // J
            'Catatan',        // K
        ];

        // ==== Data ====
        $no = 1;

        foreach ($histories as $row) {
            $stock  = $row->stock;
            $buyer  = optional(optional($stock)->buyer)->name;
            $vendor = $stock->vendor_name ?? null;

            $qty   = (float) $row->diff_quantity;
            $price = (float) ($row->unit_price ?? 0);
            $total = $qty * $price;

            $grandTotal += $total;

            $rows[] = [
                $no++,
                optional($row->created_at)->format('d-m-Y'),
                $buyer ?: '—',
                $vendor ?: '—',
                $stock->material_code ?: '—',
                $stock->material_name ?: '—',
                $stock->unit ?? '',
                $qty,
                $price,
                $total,
                $row->reason,
            ];
        }

        // ==== Row total ====
        if ($histories->isNotEmpty()) {
            $rows[] = []; // baris kosong sebelum total

            $rows[] = [
                null,
                null,
                null,
                null,
                null,
                null,
                'TOTAL PEMBELIAN', // G
                null,
                null,
                $grandTotal,        // J
                null,
            ];
        }

        return collect($rows);
    }

    /**
     * Format angka kolom di Excel
     */
    public function columnFormats(): array
    {
        // Pakai #,##0 supaya tampil 50.000 (tanpa desimal)
        return [
            'H' => '#,##0',  // Qty
            'I' => '#,##0',  // Harga Satuan
            'J' => '#,##0',  // Total
        ];
    }

    /**
     * Styling sheet (judul, header, border, alignment, dll)
     */
    public function styles(Worksheet $sheet)
    {
        // Merge judul & periode (A..K = 11 kolom)
        $sheet->mergeCells('A1:K1');
        $sheet->mergeCells('A2:K2');

        // Judul
        $sheet->getStyle('A1')->getFont()
            ->setBold(true)
            ->setSize(14);
        $sheet->getStyle('A1')->getAlignment()
            ->setHorizontal('center');

        // Periode
        $sheet->getStyle('A2')->getAlignment()
            ->setHorizontal('center');

        // Header (row 4)
        $sheet->getStyle('A4:K4')->getFont()->setBold(true);
        $sheet->getStyle('A4:K4')->getAlignment()
            ->setHorizontal('center');
        $sheet->getStyle('A4:K4')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Warna kuning untuk judul + header
        $yellow = 'FFFFFF00';

        $sheet->getStyle('A1:K2')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($yellow);

        $sheet->getStyle('A4:K4')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($yellow);

        // Border semua data + total
        $highestRow = $sheet->getHighestRow();
        if ($highestRow >= 4) {
            $sheet->getStyle("A4:K{$highestRow}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        }

        // Alignment per kolom data (mulai baris 5)
        if ($highestRow >= 5) {
            $dataStart = 5;
            $dataEnd   = $highestRow;

            // No, Tanggal, Unit center
            $sheet->getStyle("A{$dataStart}:A{$dataEnd}")
                ->getAlignment()->setHorizontal('center');
            $sheet->getStyle("B{$dataStart}:B{$dataEnd}")
                ->getAlignment()->setHorizontal('center');
            $sheet->getStyle("G{$dataStart}:G{$dataEnd}")
                ->getAlignment()->setHorizontal('center');

            // Qty, Harga, Total right
            $sheet->getStyle("H{$dataStart}:H{$dataEnd}")
                ->getAlignment()->setHorizontal('right');
            $sheet->getStyle("I{$dataStart}:I{$dataEnd}")
                ->getAlignment()->setHorizontal('right');
            $sheet->getStyle("J{$dataStart}:J{$dataEnd}")
                ->getAlignment()->setHorizontal('right');

            // Text kolom lain left (C, D, E, F, K)
            $sheet->getStyle("C{$dataStart}:F{$dataEnd}")
                ->getAlignment()->setHorizontal('left');
            $sheet->getStyle("K{$dataStart}:K{$dataEnd}")
                ->getAlignment()->setHorizontal('left');
        }

        // Total row (baris terakhir, kalau ada data)
        if ($highestRow > 5) {
            $sheet->getStyle("A{$highestRow}:K{$highestRow}")
                ->getFont()
                ->setBold(true);

            // Warna abu-abu muda untuk baris total
            $sheet->getStyle("A{$highestRow}:K{$highestRow}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF0F0F0');

            // Pastikan nilai total (kolom J) rata kanan
            $sheet->getStyle("J{$highestRow}")
                ->getAlignment()->setHorizontal('right');
        }

        return [];
    }

    /**
     * Nama sheet
     */
    public function title(): string
    {
        return 'Laporan FOB';
    }

    /**
     * Event tambahan: freeze header, lebar kolom catatan
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Tidak ada AutoFilter sama sekali.
                // Kalau di file Excel masih terlihat ikon filter di "No",
                // itu dari Excel (misal file lama, atau user aktifkan manual).

                // Freeze header (judul & header tetap saat scroll)
                $sheet->freezePane('A5'); // baris 1-4 tetap

                // Kolom catatan lebih lebar
                $sheet->getColumnDimension('K')->setWidth(40);
            },
        ];
    }
}
