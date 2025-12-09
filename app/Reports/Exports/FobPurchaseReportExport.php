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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
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
     * Data yang akan di-export ke Excel.
     * Sekaligus kita bentuk:
     *  - Row 1 : Judul
     *  - Row 2 : Periode
     *  - Row 3 : Kosong
     *  - Row 4 : Header kolom
     *  - Row 5..N : Data
     *  - Row N+1 : Total Pembelian
     */
    public function collection(): Collection
    {
        // ==== Hitung range & label periode ====
        if ($this->rangeType === 'month') {
            // Per bulan
            try {
                $monthCarbon = $this->month
                    ? Carbon::createFromFormat('Y-m', $this->month)
                    : Carbon::today();
            } catch (\Exception $e) {
                $monthCarbon = Carbon::today();
            }

            $start        = $monthCarbon->copy()->startOfMonth();
            $end          = $monthCarbon->copy()->endOfMonth();
            $periodLabel  = 'Bulan ' . $monthCarbon->format('m-Y');
        } else {
            // Per tanggal
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

        // ==== Row 1–3: judul & periode ====
        $rows[] = ['LAPORAN PEMBELIAN STOK FOB'];  // Row 1 (A1)
        $rows[] = ['Periode: ' . $periodLabel];    // Row 2 (A2)
        $rows[] = [];                              // Row 3 kosong

        // ==== Row 4: header kolom ====
        $rows[] = [
            'No',
            'Tanggal',
            'Buyer',
            'Kode',
            'Material',
            'Unit',
            'Qty',
            'Harga Satuan',
            'Total',
            'Catatan',
        ];

        // ==== Data row ====
        foreach ($histories as $index => $row) {
            $stock = $row->stock;
            $buyer = optional(optional($stock)->buyer)->name;

            $qty   = (float) $row->diff_quantity;
            $price = (float) ($row->unit_price ?? 0);
            $total = $qty * $price;

            $grandTotal += $total;

            $rows[] = [
                $index + 1,
                optional($row->created_at)->format('d-m-Y'),
                $buyer ?: '—',
                $stock->material_code ?? '—',
                $stock->material_name ?? '—',
                $stock->unit ?? '',
                $qty,
                $price,
                $total,
                $row->reason,
            ];
        }

        // ==== Row total ====
        if (!empty($histories)) {
            $rows[] = []; // 1 baris kosong sebelum total

            $rows[] = [
                null,
                null,
                null,
                null,
                null,
                'TOTAL PEMBELIAN',
                null,
                null,
                $grandTotal,
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
        return [
            // G = Qty, H = Harga Satuan, I = Total
            'G' => NumberFormat::FORMAT_NUMBER,       // Qty tanpa desimal “aneh”
            'H' => '#,##0',                           // Harga: ribuan
            'I' => '#,##0',                           // Total: ribuan
        ];
    }

    /**
     * Styling sheet (judul, header, border, dll)
     */
    public function styles(Worksheet $sheet)
    {
        // Merge judul & periode
        $sheet->mergeCells('A1:J1');
        $sheet->mergeCells('A2:J2');

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
        $sheet->getStyle('A4:J4')->getFont()->setBold(true);
        $sheet->getStyle('A4:J4')->getAlignment()
            ->setHorizontal('center');
        $sheet->getStyle('A4:J4')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // === Warna kuning untuk judul + periode + header ===
        $yellow = 'FFFFFF00'; // ARGB kuning terang

        $sheet->getStyle('A1:J2')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($yellow);

        $sheet->getStyle('A4:J4')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($yellow);

        // Border semua data + total
        $highestRow = $sheet->getHighestRow();
        if ($highestRow >= 4) {
            $sheet->getStyle("A4:J{$highestRow}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        }

        // Total row (baris terakhir, kalau ada data)
        if ($highestRow > 5) {
            $sheet->getStyle("A{$highestRow}:J{$highestRow}")
                ->getFont()
                ->setBold(true);
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
     * Event tambahan: auto filter, freeze header
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Auto filter di header (row 4)
                $sheet->setAutoFilter("A4:J4");

                // Freeze header (judul & header tetap saat scroll)
                // Freeze di A5 → baris 1–4 tetap
                $sheet->freezePane('A5');

                // Sedikit padding kolom Catatan
                $sheet->getColumnDimension('J')->setWidth(40);
            },
        ];
    }
}
