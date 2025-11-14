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
 * Sheet OUT (Pengeluaran) — tampil jam + 3 nama (opsional)
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

        if ($this->supplierId) $base->where('supplier_id', $this->supplierId);
        if ($this->q !== null && trim($this->q) !== '') $base->search($this->q);

        return $base->orderBy('moved_at','desc')->orderBy('id','desc')->get();
    }

    public function headings(): array
    {
        $head = [
            'Tanggal',   // dengan jam
            'Jenis',
            'Supplier',
            'No PO',
            'Kode',
            'Material',
            'Unit',
            'Qty',
            'Catatan',
        ];

        if ($this->showNames) {
            $head[] = 'Produksi';
            $head[] = 'Checker Gudang';
            $head[] = 'Leader Gudang';
            $head[] = 'Supply Chain Head';
        }

        return $head;
    }

    public function map($r): array
    {
        $supplier = optional($r->supplier)->name ?? '';
        $code     = optional($r->stock)->material_code ?? '';
        $unit     = $r->unit ?? (optional($r->stock)->unit ?? '');
        $material = $r->material_name ?? (optional($r->stock)->material_name ?? $r->material);

        $row = [
            optional($r->moved_at)?->format('Y-m-d H:i:s'), // dengan jam
            $r->direction,
            $supplier,
            (string) $r->po_number,
            $code,
            $material,
            $unit,
            (float) $r->quantity, // kita format TANPA desimal di AfterSheet
            (string) ($r->notes ?? ''),
        ];

        if ($this->showNames) {
            $ord   = $r->resolvedOrder;
            $row[] = $ord->production_name       ?? '';
            $row[] = $ord->warehouse_admin_name  ?? '';
            $row[] = $ord->warehouse_leader_name ?? '';
            $row[] = $ord->supply_chain_head_name ?? '';
        }

        return $row;
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 20, 'B' => 10, 'C' => 22, 'D' => 14, 'E' => 14,
            'F' => 34, 'G' => 9,  'H' => 12, 'I' => 36,
        ];
        if ($this->showNames) {
            $widths += [
                'J' => 22,  // Produksi
                'K' => 22,  // Checker Gudang
                'L' => 22,  // Leader Gudang
                'M' => 22,  // Supply Chain Head
            ];
        }
        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $this->showNames ? 'M' : 'I';
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font'=>['bold'=>true],
            'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F3F4F6']],
            'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
            'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'DDDDDD']]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $s       = $e->sheet->getDelegate();
                $lastCol = $this->showNames ? 'M' : 'I';

                // Judul
                $s->setCellValue('A1', 'LAPORAN PERGERAKAN STOK — PENGELUARAN (OUT)');
                $s->mergeCells("A1:{$lastCol}1");
                $s->getStyle('A1')->applyFromArray([
                    'font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'2E7D32']],
                ]);
                $s->getRowDimension(1)->setRowHeight(28);

                // Periode
                $period = 'Periode '.Carbon::parse($this->dateFrom)->format('d-m-Y').' s/d '.Carbon::parse($this->dateTo)->format('d-m-Y');
                $s->setCellValue('A2', $period);
                $s->mergeCells("A2:{$lastCol}2");
                $s->getStyle('A2')->applyFromArray([
                    'font'=>['bold'=>true,'size'=>11],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);

                // Info filter
                $info = 'Jenis: OUT'
                      . ($this->supplierId ? " | Supplier ID: {$this->supplierId}" : '')
                      . ($this->q ? " | Cari: {$this->q}" : '')
                      . ($this->showNames ? " | Tampilkan 3 nama" : '');
                $s->setCellValue('A3', $info);
                $s->mergeCells("A3:{$lastCol}3");

                // Border isi
                $maxRow = $s->getHighestRow();
                if ($maxRow >= 5) {
                    $s->getStyle("A4:{$lastCol}{$maxRow}")->applyFromArray([
                        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_HAIR,'color'=>['rgb'=>'CCCCCC']]],
                        'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                }

                // Format kolom: tanggal + qty tanpa desimal
                $s->getStyle("A5:A{$maxRow}")->getNumberFormat()->setFormatCode('dd-mm-yyyy hh:mm');  // dengan jam
                $s->getStyle("H5:H{$maxRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER); // tanpa desimal
                $s->getStyle("H5:H{$maxRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Autofilter + freeze
                $s->setAutoFilter("A4:{$lastCol}4");
                $s->freezePane('A5');

                // Ratakan center kolom kecil
                foreach (['B','D','E','G'] as $col) {
                    if (ord($col) <= ord($lastCol)) {
                        $s->getStyle("{$col}5:{$col}{$maxRow}")
                          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }
            },
        ];
    }
}

/**
 * Sheet IN (Pemasukan) — tanggal tanpa jam, tidak ada kolom 3 nama
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

        if ($this->supplierId) $base->where('supplier_id', $this->supplierId);
        if ($this->q !== null && trim($this->q) !== '') $base->search($this->q);

        return $base->orderBy('moved_at','desc')->orderBy('id','desc')->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal',   // TANPA jam
            'Jenis',
            'Supplier',
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
        $supplier = optional($r->supplier)->name ?? '';
        $code     = optional($r->stock)->material_code ?? '';
        $unit     = $r->unit ?? (optional($r->stock)->unit ?? '');
        $material = $r->material_name ?? (optional($r->stock)->material_name ?? $r->material);

        return [
            optional($r->moved_at)?->format('Y-m-d'), // TANPA jam
            $r->direction,
            $supplier,
            (string) $r->po_number,
            $code,
            $material,
            $unit,
            (float) $r->quantity, // kita format TANPA desimal di AfterSheet
            (string) ($r->notes ?? ''),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A'=>16,'B'=>10,'C'=>22,'D'=>14,'E'=>14,'F'=>34,'G'=>9,'H'=>12,'I'=>36,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle("A4:I4")->applyFromArray([
            'font'=>['bold'=>true],
            'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F3F4F6']],
            'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
            'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'DDDDDD']]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $s = $e->sheet->getDelegate();
                $lastCol = 'I';

                // Judul
                $s->setCellValue('A1', 'LAPORAN PERGERAKAN STOK — PEMASUKAN (IN)');
                $s->mergeCells("A1:{$lastCol}1");
                $s->getStyle('A1')->applyFromArray([
                    'font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1E88E5']], // biru
                ]);
                $s->getRowDimension(1)->setRowHeight(28);

                // Periode (tanggal saja)
                $period = 'Periode '.Carbon::parse($this->dateFrom)->format('d-m-Y').' s/d '.Carbon::parse($this->dateTo)->format('d-m-Y');
                $s->setCellValue('A2', $period);
                $s->mergeCells("A2:{$lastCol}2");
                $s->getStyle('A2')->applyFromArray([
                    'font'=>['bold'=>true,'size'=>11],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);

                // Info filter
                $info = 'Jenis: IN'
                      . ($this->supplierId ? " | Supplier ID: {$this->supplierId}" : '')
                      . ($this->q ? " | Cari: {$this->q}" : '');
                $s->setCellValue('A3', $info);
                $s->mergeCells("A3:{$lastCol}3");

                // Border isi
                $maxRow = $s->getHighestRow();
                if ($maxRow >= 5) {
                    $s->getStyle("A4:{$lastCol}{$maxRow}")->applyFromArray([
                        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_HAIR,'color'=>['rgb'=>'CCCCCC']]],
                        'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                }

                // Format tanggal TANPA jam + qty TANPA desimal
                $s->getStyle("A5:A{$maxRow}")->getNumberFormat()->setFormatCode('dd-mm-yyyy');
                $s->getStyle("H5:H{$maxRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER); // tanpa desimal
                $s->getStyle("H5:H{$maxRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Autofilter + freeze
                $s->setAutoFilter("A4:{$lastCol}4");
                $s->freezePane('A5');

                // Ratakan center kolom kecil
                foreach (['B','D','E','G'] as $col) {
                    $s->getStyle("{$col}5:{$col}{$maxRow}")
                      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}
