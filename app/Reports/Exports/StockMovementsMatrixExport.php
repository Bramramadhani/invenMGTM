<?php

namespace App\Reports\Exports;

use App\Models\StockMovement;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMovementsMatrixExport implements FromArray, WithEvents, WithTitle
{
    protected string $dateFrom;
    protected string $dateTo;
    protected $supplierId;
    protected string $q;
    protected bool $showNames;

    protected array $rows = [];
    protected array $headDates = [];
    protected int $totalCols = 0;

    public function __construct(
        ?string $dateFrom,
        ?string $dateTo,
        $supplierId = null,
        string $q = '',
        bool $showNames = true   // default true: tampilkan 3 nama
    ) {
        $this->dateFrom   = $dateFrom ?: Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo     = $dateTo   ?: Carbon::now()->endOfMonth()->toDateString();
        $this->supplierId = $supplierId;
        $this->q          = trim($q);
        $this->showNames  = $showNames;
    }

    public function array(): array
    {
        $start = Carbon::parse($this->dateFrom)->startOfDay();
        $end   = Carbon::parse($this->dateTo)->endOfDay();

        // daftar tanggal
        $this->headDates = [];
        for ($d = $start->clone(); $d->lte($end); $d->addDay()) {
            $this->headDates[] = $d->format('Y-m-d');
        }

        // data pergerakan (ikut relasi agar bisa ambil 3 nama)
        $base = StockMovement::query()
            ->with([
                'supplier:id,name',
                'stock:id,material_code,material_name,unit,supplier_id,unit_cost,cost',
                    'order:id,production_name,warehouse_admin_name,warehouse_leader_name,supply_chain_head_name',
                    'orderItem.order:id,production_name,warehouse_admin_name,warehouse_leader_name,supply_chain_head_name',
            ])
            ->whereBetween('moved_at', [$start, $end]);

        if (!empty($this->supplierId)) $base->where('supplier_id', $this->supplierId);
        if ($this->q !== '') $base->search($this->q);

        $movements = $base->orderBy('stock_id')->orderBy('moved_at')->get();

        // stok awal (signed quantity sampai H-1)
        $openingBase = StockMovement::query()
            ->with('stock:id')
            ->where('moved_at', '<', $start);
        if (!empty($this->supplierId)) $openingBase->where('supplier_id', $this->supplierId);
        if ($this->q !== '') $openingBase->search($this->q);

        $openings = $openingBase->get()->groupBy('stock_id')->map(function ($grp) {
            return (float) $grp->sum(function ($m) {
                return $m->direction === StockMovement::DIR_OUT
                    ? -1 * (float) $m->quantity
                    : (float) $m->quantity;
            });
        });

        // kelompok per stok
        $byStock = $movements->groupBy('stock_id');
        $no = 0;

        foreach ($byStock as $stockId => $list) {
            $first    = $list->first();
            $stock    = $first->stock;
            $supplier = $first->supplier;

            $materialCode = optional($stock)->material_code ?: '';
            $materialName = optional($stock)->material_name ?: ($first->material_name ?? '');
            $unit         = optional($stock)->unit ?: ($first->unit ?? '');
            $suppName     = optional($supplier)->name ?: '';

            // ambil harga satuan jika ada (ubah sesuai kolom punyamu)
            $unitCost = (float) (optional($stock)->unit_cost ?? optional($stock)->cost ?? 0);

            // 3 NAMA: ambil dari movement OUT terbaru dalam periode yang punya order
            $prod = $checker = $leader = '';
            if ($this->showNames) {
                foreach ($list->sortByDesc('moved_at') as $m) {
                    if ($m->direction !== StockMovement::DIR_OUT) continue;
                    $ord = $m->resolvedOrder; // accessor dari model
                    if ($ord) {
                                $prod    = (string) ($ord->production_name ?? '');
                                $checker = (string) ($ord->warehouse_admin_name ?? '');
                                $leader  = (string) ($ord->warehouse_leader_name ?? '');
                                $supply  = (string) ($ord->supply_chain_head_name ?? '');
                        break;
                    }
                }
            }

            // stok awal
            $stokAwal = (float) ($openings[$stockId] ?? 0);

            // matriks harian
            $harian = [];
            foreach ($this->headDates as $yd) {
                $harian[$yd] = ['in' => 0.0, 'out' => 0.0];
            }

            foreach ($list as $m) {
                $yd = Carbon::parse($m->moved_at)->format('Y-m-d');
                if (!isset($harian[$yd])) continue;
                $qty = (float) $m->quantity;
                if ($m->direction === StockMovement::DIR_IN)  $harian[$yd]['in']  += $qty;
                if ($m->direction === StockMovement::DIR_OUT) $harian[$yd]['out'] += $qty;
            }

            // sisa & nilai akhir
            $saldo = $stokAwal;
            foreach ($this->headDates as $yd) {
                $saldo += ($harian[$yd]['in'] - $harian[$yd]['out']);
            }
            $nilaiAkhir = $saldo * $unitCost;

            // row: No | Kode | Nama | (3 Nama) | Harga | Stok Awal | [tgl: In | Out]* | Sisa | Nilai
            $row = [
                ++$no,
                $materialCode,
                $materialName,
            ];

            if ($this->showNames) {
                $row[] = $prod;
                $row[] = $checker;
                $row[] = $leader;
                $row[] = $supply ?? '';
            }

            $row[] = $unitCost;
            $row[] = $stokAwal;

            foreach ($this->headDates as $yd) {
                $row[] = (float) $harian[$yd]['in'];
                $row[] = (float) $harian[$yd]['out'];
            }

            $row[] = $saldo;
            $row[] = $nilaiAkhir;

            $this->rows[] = $row;
        }

        // header baris (akan di-style & merge di AfterSheet)
        $headerTop = ['No', 'Kode Barang', 'Nama Barang'];
        if ($this->showNames) {
            $headerTop[] = 'Produksi';
            $headerTop[] = 'Checker Gudang';
            $headerTop[] = 'Leader Gudang';
            $headerTop[] = 'Supply Chain Head';
        }
        $headerTop[] = 'Harga Barang';
        $headerTop[] = 'Stok Awal';
        foreach ($this->headDates as $yd) { $headerTop[] = $yd; $headerTop[] = ''; }
        $headerTop[] = 'Sisa Stok';
        $headerTop[] = 'Nilai Persediaan Akhir';

        $headerSub = array_fill(0, count($headerTop), '');
        // sub header untuk tanggal
        $staticCount = 3 + ($this->showNames ? 3 : 0) + 2; // No,Kode,Nama,(3nama),Harga,StokAwal
        for ($i = 0; $i < count($this->headDates); $i++) {
            $headerSub[$staticCount + ($i*2)]     = 'Barang Masuk';
            $headerSub[$staticCount + ($i*2) + 1] = 'Barang Keluar';
        }

        $this->totalCols = count($headerTop);

        return [
            ['LAPORAN STOK BARANG'],
            ['Periode ' . Carbon::parse($this->dateFrom)->format('d M Y') . ' - ' . Carbon::parse($this->dateTo)->format('d M Y')],
            [''],
            $headerTop,
            $headerSub,
            ...$this->rows,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastCol = Coordinate::stringFromColumnIndex($this->totalCols);
                $lastRow = 5 + max(1, count($this->rows));

                // judul
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->mergeCells("A2:{$lastCol}2");

                $sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF5E9E4D');

                $sheet->getStyle("A2")->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF8DC37F');

                // header top
                $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFEFEF']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                // header sub
                $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF7F7F7']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // merge kolom statis
                $staticColsCount = 3 + ($this->showNames ? 3 : 0) + 2; // No,Kode,Nama,(3nama),Harga,StokAwal
                for ($i = 1; $i <= $staticColsCount; $i++) {
                    $col = Coordinate::stringFromColumnIndex($i);
                    $sheet->mergeCells("{$col}4:{$col}5");
                }
                // Sisa & Nilai
                $sheet->mergeCells(Coordinate::stringFromColumnIndex($this->totalCols - 1) . "4:" .
                                   Coordinate::stringFromColumnIndex($this->totalCols - 1) . "5");
                $sheet->mergeCells(Coordinate::stringFromColumnIndex($this->totalCols) . "4:" .
                                   Coordinate::stringFromColumnIndex($this->totalCols) . "5");

                // merge grup tanggal (2 kolom per hari)
                $startIdx = $staticColsCount + 1;
                foreach ($this->headDates as $i => $d) {
                    $L = Coordinate::stringFromColumnIndex($startIdx + ($i * 2));
                    $R = Coordinate::stringFromColumnIndex($startIdx + ($i * 2) + 1);
                    $sheet->mergeCells("{$L}4:{$R}4");
                }

                // border data
                if (count($this->rows) > 0) {
                    $sheet->getStyle("A6:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                }

                // width
                $setW = fn(string $col, float $w) => $sheet->getColumnDimension($col)->setWidth($w);
                $setW('A', 6);   // No
                $setW('B', 14);  // Kode
                $setW('C', 30);  // Nama
                $cur = 4;
                if ($this->showNames) {
                    $setW('D', 18); // Produksi
                    $setW('E', 18); // Checker
                    $setW('F', 18); // Leader
                    $cur = 7;
                }
                $setW(Coordinate::stringFromColumnIndex($cur++), 12); // Harga
                $setW(Coordinate::stringFromColumnIndex($cur++), 10); // Stok awal
                // tanggal
                for ($i = 0; $i < count($this->headDates) * 2; $i++) {
                    $setW(Coordinate::stringFromColumnIndex($cur++), 12);
                }
                $setW(Coordinate::stringFromColumnIndex($cur++), 10); // Sisa
                $setW(Coordinate::stringFromColumnIndex($cur++), 18); // Nilai

                // number format right aligned
                // tentukan kolom angka mulai dari (Harga)
                $numStartIdx = 4 + ($this->showNames ? 3 : 0);
                for ($i = $numStartIdx; $i <= $this->totalCols; $i++) {
                    $col = Coordinate::stringFromColumnIndex($i);
                    $sheet->getStyle("{$col}6:{$col}{$lastRow}")
                          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("{$col}6:{$col}{$lastRow}")
                          ->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // wrap untuk teks panjang
                $sheet->getStyle("C6:C{$lastRow}")->getAlignment()->setWrapText(true);
                if ($this->showNames) {
                    $sheet->getStyle("D6:F{$lastRow}")->getAlignment()->setWrapText(true);
                }

                // freeze
                $sheet->freezePane('A6');

                // tinggi baris judul
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(4)->setRowHeight(22);
                $sheet->getRowDimension(5)->setRowHeight(22);
            },
        ];
    }

    public function title(): string
    {
        return 'Laporan Stok (Matriks)';
    }
}
