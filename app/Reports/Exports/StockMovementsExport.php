<?php

namespace App\Reports\Exports;

use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Export utama: membuat 2 sheet (OUT & IN)
 */
class StockMovementsExport implements WithMultipleSheets
{
    public function __construct(
        protected string $dateFrom,
        protected string $dateTo,
        protected ?string $supplierId,
        protected ?string $q,
        protected string $type,          // all|in|out
        protected bool $showNames = false
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        if ($this->type !== 'in') {
            $sheets[] = new MovementsOutSheetExport(
                $this->dateFrom, $this->dateTo, $this->supplierId, $this->q, $this->showNames
            );
        }

        if ($this->type !== 'out') {
            $sheets[] = new MovementsInSheetExport(
                $this->dateFrom, $this->dateTo, $this->supplierId, $this->q
            );
        }

        return $sheets;
    }
}

/**
 * Sheet OUT (Pengeluaran) — tampil jam + nama Produksi & Gudang (opsional)
 */
class MovementsOutSheetExport implements
    FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithStyles, WithEvents, WithCustomStartCell, WithTitle
{
    public function __construct(
        protected string $dateFrom,
        protected string $dateTo,
        protected ?string $supplierId,
        protected ?string $q,
        protected bool $showNames = false
    ) {}

    public function startCell(): string { return 'A4'; }

    public function title(): string { return 'Out'; }

    public function collection(): Collection
    {
        $base = StockMovement::query()
            ->withReportRelations()
            ->where('direction', StockMovement::DIR_OUT)
            ->whereDate('moved_at', '>=', $this->dateFrom)
            ->whereDate('moved_at', '<=', $this->dateTo);

        if ($this->supplierId) {
            $base->where('supplier_id', $this->supplierId);
        }
        if ($this->q !== null && trim($this->q) !== '') {
            $base->search($this->q);
        }

        return $base
            ->orderBy('moved_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function headings(): array
    {
        // Tambah kolom Style + Leader Produksi
        $head = [
            'Tanggal',   // dengan jam
            'Jenis',
            'Buyer',
            'No PO',
            'Style',
            'Kode',
            'Material',
            'Unit',
            'Qty',
            'Catatan',
        ];

        if ($this->showNames) {
            $head[] = 'Checker Produksi';
            $head[] = 'Leader Produksi';
            $head[] = 'Checker Gudang';
            $head[] = 'Leader Gudang';
            $head[] = 'Supply Chain Head';
        }

        return $head;
    }

    public function map($r): array
    {
        $buyerName = optional($r->supplier)->name
            ?: optional(optional($r->stock)->buyer)->name
            ?: '';
        $code     = optional($r->stock)->material_code ?? '';
        $unit     = $r->unit ?? (optional($r->stock)->unit ?? '');
        $material = $r->material_name ?? (optional($r->stock)->material_name ?? $r->material);

        $order    = $r->resolvedOrder;

        // Ambil nama style dari Order -> purchaseOrderStyle (fleksibel kolom)
        $styleObj  = optional(optional($order)->purchaseOrderStyle);
        $styleName = $styleObj->style_name
            ?? $styleObj->name
            ?? $styleObj->nama_style
            ?? '';

        $row = [
            optional($r->moved_at)?->format('Y-m-d H:i:s'), // dengan jam
            $r->direction,
            $buyerName,
            (string) $r->po_number,
            $styleName,
            $code,
            $material,
            $unit,
            (float) $r->quantity,
            (string) ($r->notes ?? ''),
        ];

        if ($this->showNames) {
            $row[] = optional($order)->production_name         ?? '';
            $row[] = optional($order)->production_leader_name  ?? '';
            $row[] = optional($order)->warehouse_admin_name    ?? '';
            $row[] = optional($order)->warehouse_leader_name   ?? '';
            $row[] = optional($order)->supply_chain_head_name  ?? '';
        }

        return $row;
    }

    public function columnWidths(): array
    {
        // Susunan: A:Tgl, B:Jenis, C:Buyer, D:No PO, E:Style, F:Kode, G:Material, H:Unit, I:Qty, J:Catatan
        $widths = [
            'A' => 20,
            'B' => 10,
            'C' => 22,
            'D' => 14,
            'E' => 22, // Style
            'F' => 14,
            'G' => 34,
            'H' => 9,
            'I' => 12,
            'J' => 36,
        ];

        if ($this->showNames) {
            // K:Produksi, L:Leader Produksi, M:Checker Gudang, N:Leader Gudang, O:Supply Chain Head
            $widths += [
                'K' => 22,
                'L' => 22,
                'M' => 22,
                'N' => 22,
                'O' => 22,
            ];
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColIndex = count($this->headings());
        $lastCol      = Coordinate::stringFromColumnIndex($lastColIndex);

        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders'   => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'DDDDDD'],
                ],
            ],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $s             = $e->sheet->getDelegate();
                $lastColIndex  = count($this->headings());
                $lastCol       = Coordinate::stringFromColumnIndex($lastColIndex);

                // Judul
                $s->setCellValue('A1', 'LAPORAN PERGERAKAN STOK — PENGELUARAN (OUT)');
                $s->mergeCells("A1:{$lastCol}1");
                $s->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2E7D32'],
                    ],
                ]);
                $s->getRowDimension(1)->setRowHeight(28);

                // Periode
                $period = 'Periode '
                    . Carbon::parse($this->dateFrom)->format('d-m-Y')
                    . ' s/d '
                    . Carbon::parse($this->dateTo)->format('d-m-Y');

                $s->setCellValue('A2', $period);
                $s->mergeCells("A2:{$lastCol}2");
                $s->getStyle('A2')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Info filter
                $info = 'Jenis: OUT'
                    . ($this->supplierId ? " | Buyer ID: {$this->supplierId}" : '')
                    . ($this->q ? " | Cari: {$this->q}" : '')
                    . ($this->showNames ? " | Tampilkan nama Produksi & Gudang" : '');

                $s->setCellValue('A3', $info);
                $s->mergeCells("A3:{$lastCol}3");

                // Border isi
                $maxRow = $s->getHighestRow();
                if ($maxRow >= 5) {
                    $s->getStyle("A4:{$lastCol}{$maxRow}")->applyFromArray([
                        'borders'   => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_HAIR,
                                'color'       => ['rgb' => 'CCCCCC'],
                            ],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                // Format kolom: tanggal + qty tanpa desimal
                if ($maxRow >= 5) {
                    // A: tanggal+jam
                    $s->getStyle("A5:A{$maxRow}")
                        ->getNumberFormat()
                        ->setFormatCode('dd-mm-yyyy hh:mm');

                    $qtyIndex = array_search('Qty', $this->headings(), true);
                    $qtyCol   = Coordinate::stringFromColumnIndex(($qtyIndex === false ? 10 : $qtyIndex + 1));

                    // Qty (tanpa desimal, rata kanan)
                    $s->getStyle("{$qtyCol}5:{$qtyCol}{$maxRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER);
                    $s->getStyle("{$qtyCol}5:{$qtyCol}{$maxRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Autofilter + freeze
                $s->setAutoFilter("A4:{$lastCol}4");
                $s->freezePane('A5');

                // Ratakan center kolom kecil
                foreach (['B', 'D', 'E', 'F', 'H'] as $col) {
                    if (ord($col) <= ord($lastCol)) {
                        $s->getStyle("{$col}5:{$col}{$maxRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }
            },
        ];
    }
}

/**
 * Sheet IN (Pemasukan) — tanggal tanpa jam, tidak ada kolom nama
 */
class MovementsInSheetExport implements
    FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithStyles, WithEvents, WithCustomStartCell, WithTitle
{
    public function __construct(
        protected string $dateFrom,
        protected string $dateTo,
        protected ?string $supplierId,
        protected ?string $q
    ) {}

    public function startCell(): string { return 'A4'; }

    public function title(): string { return 'In'; }

    public function collection(): Collection
    {
        $base = StockMovement::query()
            ->withReportRelations()
            ->where('direction', StockMovement::DIR_IN)
            ->whereDate('moved_at', '>=', $this->dateFrom)
            ->whereDate('moved_at', '<=', $this->dateTo);

        if ($this->supplierId) {
            $base->where('supplier_id', $this->supplierId);
        }
        if ($this->q !== null && trim($this->q) !== '') {
            $base->search($this->q);
        }

        return $base
            ->orderBy('moved_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal',   // TANPA jam
            'Jenis',
            'Buyer',
            'No PO',
            'Kode',
            'Material',
            'Unit',
            'Qty',
            'Catatan',
        ];
    }

    public function map($r): array
    {
        $buyerName = optional($r->supplier)->name
            ?: optional(optional($r->stock)->buyer)->name
            ?: '';
        $code     = optional($r->stock)->material_code ?? '';
        $unit     = $r->unit ?? (optional($r->stock)->unit ?? '');
        $material = $r->material_name ?? (optional($r->stock)->material_name ?? $r->material);

        return [
            optional($r->moved_at)?->format('Y-m-d'), // TANPA jam
            $r->direction,
            $buyerName,
            (string) $r->po_number,
            $code,
            $material,
            $unit,
            (float) $r->quantity,
            (string) ($r->notes ?? ''),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 10,
            'C' => 22,
            'D' => 14,
            'E' => 14,
            'F' => 34,
            'G' => 9,
            'H' => 12,
            'I' => 36,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastColIndex = count($this->headings());
        $lastCol      = Coordinate::stringFromColumnIndex($lastColIndex);

        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders'   => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'DDDDDD'],
                ],
            ],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $s       = $e->sheet->getDelegate();
                $lastColIndex = count($this->headings());
                $lastCol      = Coordinate::stringFromColumnIndex($lastColIndex);

                // Judul
                $s->setCellValue('A1', 'LAPORAN PERGERAKAN STOK — PEMASUKAN (IN)');
                $s->mergeCells("A1:{$lastCol}1");
                $s->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E88E5'],
                    ],
                ]);
                $s->getRowDimension(1)->setRowHeight(28);

                // Periode (tanggal saja)
                $period = 'Periode '
                    . Carbon::parse($this->dateFrom)->format('d-m-Y')
                    . ' s/d '
                    . Carbon::parse($this->dateTo)->format('d-m-Y');

                $s->setCellValue('A2', $period);
                $s->mergeCells("A2:{$lastCol}2");
                $s->getStyle('A2')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Info filter
                $info = 'Jenis: IN'
                    . ($this->supplierId ? " | Buyer ID: {$this->supplierId}" : '')
                    . ($this->q ? " | Cari: {$this->q}" : '');
                $s->setCellValue('A3', $info);
                $s->mergeCells("A3:{$lastCol}3");

                // Border isi
                $maxRow = $s->getHighestRow();
                if ($maxRow >= 5) {
                    $s->getStyle("A4:{$lastCol}{$maxRow}")->applyFromArray([
                        'borders'   => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_HAIR,
                                'color'       => ['rgb' => 'CCCCCC'],
                            ],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                // Format tanggal TANPA jam + qty TANPA desimal
                if ($maxRow >= 5) {
                    $s->getStyle("A5:A{$maxRow}")
                        ->getNumberFormat()
                        ->setFormatCode('dd-mm-yyyy');

                    $qtyIndex = array_search('Qty', $this->headings(), true);
                    $qtyCol   = Coordinate::stringFromColumnIndex(($qtyIndex === false ? 9 : $qtyIndex + 1));

                    $s->getStyle("{$qtyCol}5:{$qtyCol}{$maxRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER);
                    $s->getStyle("{$qtyCol}5:{$qtyCol}{$maxRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Autofilter + freeze
                $s->setAutoFilter("A4:{$lastCol}4");
                $s->freezePane('A5');

                // Ratakan center kolom kecil
                foreach (['B', 'D', 'E', 'G'] as $col) {
                    $s->getStyle("{$col}5:{$col}{$maxRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}
