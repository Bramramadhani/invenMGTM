<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryRepairFobDuplicates extends Command
{
    protected $signature = 'inventory:repair-fob-duplicates
        {--apply : Terapkan perubahan (default hanya audit / dry-run)}
        {--buyer_id= : Filter hanya untuk buyer tertentu}';

    protected $description = 'Audit dan perbaiki duplikat stok FOB (buyer + material + unit).';

    private array $referenceTables = [
        'order_items',
        'production_issue_items',
        'purchase_order_rejects',
        'receipt_correction_audits',
        'stock_histories',
        'stock_movements',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $buyerIdFilter = $this->option('buyer_id');
        $buyerIdFilter = $buyerIdFilter !== null && $buyerIdFilter !== '' ? (int) $buyerIdFilter : null;

        $groups = $this->findDuplicateGroups($buyerIdFilter);

        if ($groups->isEmpty()) {
            $this->info('Tidak ada duplikat stok FOB. Selesai.');
            return self::SUCCESS;
        }

        $this->warn('Ditemukan ' . $groups->count() . ' grup duplikat stok FOB.');
        if (!$apply) {
            $this->warn('Mode DRY-RUN aktif. Tidak ada perubahan data.');
        }

        $totalMergedRows = 0;
        $totalRepointedRefs = 0;

        foreach ($groups as $group) {
            $result = $this->processGroup(
                buyerId: (int) $group->buyer_id,
                materialKey: (string) $group->material_key,
                unitKey: (string) $group->unit_key,
                apply: $apply
            );

            if (!$result) {
                continue;
            }

            $totalMergedRows += $result['merged_rows'];
            $totalRepointedRefs += $result['repointed_refs'];

            $this->line(sprintf(
                '- buyer_id=%d, material="%s", unit="%s": rows=%d -> keeper=%d, qty_total=%s, repointed_refs=%d%s',
                $result['buyer_id'],
                $result['material_name'],
                $result['unit'],
                $result['rows'],
                $result['keeper_id'],
                $result['qty_total'],
                $result['repointed_refs'],
                $apply ? ', APPLY' : ', DRY-RUN'
            ));
        }

        $this->newLine();
        $this->info('Ringkasan:');
        $this->line('- Grup duplikat: ' . $groups->count());
        $this->line('- Total baris duplikat yang akan/did merge: ' . $totalMergedRows);
        $this->line('- Total referensi stock_id yang akan/did dipindah: ' . $totalRepointedRefs);

        if (!$apply) {
            $this->warn('Jalankan lagi dengan --apply untuk menerapkan perbaikan.');
        }

        return self::SUCCESS;
    }

    private function findDuplicateGroups(?int $buyerIdFilter)
    {
        return DB::table('stocks')
            ->selectRaw('buyer_id, UPPER(TRIM(material_name)) AS material_key, UPPER(TRIM(unit)) AS unit_key, COUNT(*) AS row_count')
            ->whereNotNull('buyer_id')
            ->whereNull('supplier_id')
            ->whereNull('purchase_order_id')
            ->when($buyerIdFilter, fn ($q) => $q->where('buyer_id', $buyerIdFilter))
            ->groupBy('buyer_id')
            ->groupByRaw('UPPER(TRIM(material_name))')
            ->groupByRaw('UPPER(TRIM(unit))')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('buyer_id')
            ->get();
    }

    private function loadGroupStocks(int $buyerId, string $materialKey, string $unitKey, bool $forUpdate = false)
    {
        $query = Stock::query()
            ->whereNotNull('buyer_id')
            ->whereNull('supplier_id')
            ->whereNull('purchase_order_id')
            ->where('buyer_id', $buyerId)
            ->whereRaw('UPPER(TRIM(material_name)) = ?', [$materialKey])
            ->whereRaw('UPPER(TRIM(unit)) = ?', [$unitKey])
            ->orderBy('id');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function processGroup(int $buyerId, string $materialKey, string $unitKey, bool $apply): ?array
    {
        $stocks = $this->loadGroupStocks($buyerId, $materialKey, $unitKey, false);
        if ($stocks->count() < 2) {
            return null;
        }

        $keeper = $stocks->first();
        $duplicateIds = $stocks->pluck('id')->slice(1)->values()->all();
        $totalQty = (float) $stocks->sum('quantity');

        $result = [
            'buyer_id' => $buyerId,
            'material_name' => (string) $keeper->material_name,
            'unit' => (string) $keeper->unit,
            'rows' => $stocks->count(),
            'keeper_id' => (int) $keeper->id,
            'qty_total' => number_format($totalQty, 4, '.', ''),
            'merged_rows' => count($duplicateIds),
            'repointed_refs' => 0,
        ];

        if (!$apply) {
            return $result;
        }

        DB::transaction(function () use (&$result, $buyerId, $materialKey, $unitKey) {
            $lockedStocks = $this->loadGroupStocks($buyerId, $materialKey, $unitKey, true);
            if ($lockedStocks->count() < 2) {
                $result['merged_rows'] = 0;
                $result['repointed_refs'] = 0;
                return;
            }

            /** @var \App\Models\Stock $keeperStock */
            $keeperStock = $lockedStocks->first();
            $duplicateIds = $lockedStocks->pluck('id')->slice(1)->values()->all();
            $sumQty = (float) $lockedStocks->sum('quantity');

            $latestWithCode = $lockedStocks->first(fn ($s) => !empty($s->material_code));
            $latestWithVendor = $lockedStocks->first(fn ($s) => !empty($s->vendor_name));

            $repointed = 0;
            foreach ($this->referenceTables as $table) {
                if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'stock_id')) {
                    continue;
                }
                $repointed += DB::table($table)
                    ->whereIn('stock_id', $duplicateIds)
                    ->update(['stock_id' => $keeperStock->id]);
            }

            $keeperStock->quantity = $sumQty;
            if (empty($keeperStock->material_code) && $latestWithCode) {
                $keeperStock->material_code = $latestWithCode->material_code;
            }
            if (empty($keeperStock->vendor_name) && $latestWithVendor) {
                $keeperStock->vendor_name = $latestWithVendor->vendor_name;
            }
            $keeperStock->save();

            Stock::whereIn('id', $duplicateIds)->delete();

            $result['rows'] = $lockedStocks->count();
            $result['keeper_id'] = (int) $keeperStock->id;
            $result['qty_total'] = number_format($sumQty, 4, '.', '');
            $result['merged_rows'] = count($duplicateIds);
            $result['repointed_refs'] = $repointed;
        });

        return $result;
    }
}

