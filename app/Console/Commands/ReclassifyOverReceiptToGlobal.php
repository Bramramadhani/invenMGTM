<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReclassifyOverReceiptToGlobal extends Command
{
    /**
     * Jalankan:
     *   php artisan stock:reclassify-overreceipt           (dry-run)
     *   php artisan stock:reclassify-overreceipt --apply   (apply)
     * Opsional:
     *   --po-id=123 atau --po-number=PO-XXX
     */
    protected $signature = 'stock:reclassify-overreceipt
                            {--apply : Apply changes (default dry-run)}
                            {--po-id= : Filter by purchase_order_id}
                            {--po-number= : Filter by po_number}
                            {--limit= : Limit number of items}';

    protected $description = 'Reclassify over-receipt quantities from PO stock to global stock (one-off fix).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $poId = $this->option('po-id');
        $poNumber = $this->option('po-number');
        $limit = $this->option('limit');

        $this->info($apply ? 'MODE: APPLY' : 'MODE: DRY-RUN');

        if ($poId) {
            $this->line("Filter PO ID: {$poId}");
        }
        if ($poNumber) {
            $this->line("Filter PO Number: {$poNumber}");
        }
        if ($limit) {
            $this->line("Limit: {$limit}");
        }

        $postedSub = DB::table('purchase_receipt_items as pri')
            ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
            ->where('pr.status', 'posted')
            ->groupBy('pri.purchase_order_item_id')
            ->selectRaw('pri.purchase_order_item_id, SUM(pri.received_quantity) AS received_total');

        $query = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->joinSub($postedSub, 'posted', function ($join) {
                $join->on('posted.purchase_order_item_id', '=', 'poi.id');
            })
            ->select([
                'poi.id as poi_id',
                'poi.purchase_order_id',
                'po.po_number',
                'po.supplier_id',
                'poi.material_name',
                'poi.material_code',
                'poi.unit',
                'poi.ordered_quantity',
                'posted.received_total',
            ])
            ->whereRaw('posted.received_total > poi.ordered_quantity')
            ->orderBy('poi.id');

        if ($poId) {
            $query->where('poi.purchase_order_id', (int) $poId);
        }
        if ($poNumber) {
            $query->where('po.po_number', (string) $poNumber);
        }
        if ($limit) {
            $query->limit((int) $limit);
        }

        $totalItems = 0;
        $movedItems = 0;
        $skippedNoStock = 0;
        $skippedNoMove = 0;
        $totalMovedQty = 0.0;

        foreach ($query->cursor() as $row) {
            $totalItems++;

            DB::transaction(function () use (
                $row,
                $apply,
                &$movedItems,
                &$skippedNoStock,
                &$skippedNoMove,
                &$totalMovedQty
            ) {
                // Lock PO row for consistency.
                DB::table('purchase_orders')
                    ->where('id', $row->purchase_order_id)
                    ->lockForUpdate()
                    ->value('id');

                $receivedNow = (float) DB::table('purchase_receipt_items as pri')
                    ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                    ->where('pr.status', 'posted')
                    ->where('pri.purchase_order_item_id', $row->poi_id)
                    ->sum('pri.received_quantity');

                $ordered = (float) $row->ordered_quantity;
                $over = $receivedNow - $ordered;
                if ($over <= 0.0000001) {
                    $skippedNoMove++;
                    return;
                }

                $poStock = Stock::where('purchase_order_id', $row->purchase_order_id)
                    ->where('supplier_id', $row->supplier_id)
                    ->where('material_name', $row->material_name)
                    ->where('unit', $row->unit)
                    ->lockForUpdate()
                    ->first();

                if (!$poStock) {
                    $skippedNoStock++;
                    return;
                }

                $poQty = (float) $poStock->quantity;
                $moveQty = min($over, $poQty);
                $moveQty = round($moveQty, 4);

                if ($moveQty <= 0.0000001) {
                    $skippedNoMove++;
                    return;
                }

                $movedItems++;
                $totalMovedQty += $moveQty;

                if (!$apply) {
                    $this->line(
                        "DRY-RUN: PO {$row->po_number} | {$row->material_name} {$row->unit} | over {$over} | move {$moveQty}"
                    );
                    return;
                }

                $materialCode = $row->material_code !== null
                    ? strtoupper(trim((string) $row->material_code))
                    : null;

                $globalStock = Stock::whereNull('purchase_order_id')
                    ->whereNull('buyer_id')
                    ->where('supplier_id', $row->supplier_id)
                    ->where('material_name', $row->material_name)
                    ->where('unit', $row->unit)
                    ->lockForUpdate()
                    ->first();

                if (!$globalStock) {
                    $globalStock = new Stock();
                    $globalStock->purchase_order_id = null;
                    $globalStock->supplier_id = $row->supplier_id;
                    $globalStock->buyer_id = null;
                    $globalStock->material_name = $row->material_name;
                    $globalStock->unit = $row->unit;
                    $globalStock->quantity = 0;
                }

                if ($materialCode) {
                    $globalStock->material_code = $materialCode;
                }
                $globalStock->last_po_id = $row->purchase_order_id;
                $globalStock->last_po_number = $row->po_number;

                $poOld = (float) $poStock->quantity;
                $poNew = max(0, $poOld - $moveQty);
                $poStock->quantity = $poNew;
                $poStock->save();

                $globOld = (float) $globalStock->quantity;
                $globNew = $globOld + $moveQty;
                $globalStock->quantity = $globNew;
                $globalStock->save();

                $reasonOut = 'Reclass over-receipt to GLOBAL from PO ' . $row->po_number;
                $reasonIn = 'Reclass over-receipt from PO ' . $row->po_number;

                StockHistory::recordChange(
                    $poStock,
                    $poOld,
                    (float) $poStock->quantity,
                    StockHistory::TYPE_MANUAL_CORRECTION,
                    $reasonOut
                );

                StockHistory::recordChange(
                    $globalStock,
                    $globOld,
                    (float) $globalStock->quantity,
                    StockHistory::TYPE_MANUAL_CORRECTION,
                    $reasonIn
                );

                StockMovement::recordOut(
                    $poStock->id,
                    (int) $poStock->supplier_id,
                    $poStock->material_name,
                    $poStock->unit,
                    $moveQty,
                    $row->po_number,
                    $reasonOut,
                    now()
                );

                StockMovement::recordIn(
                    $globalStock->id,
                    (int) $globalStock->supplier_id,
                    $globalStock->material_name,
                    $globalStock->unit,
                    $moveQty,
                    $row->po_number,
                    $reasonIn,
                    now()
                );

                $this->info(
                    "APPLIED: PO {$row->po_number} | {$row->material_name} {$row->unit} | moved {$moveQty}"
                );
            });
        }

        $this->line('---');
        $this->info("Items checked: {$totalItems}");
        $this->info("Items moved: {$movedItems}");
        $this->info('Total moved qty: ' . $totalMovedQty);
        $this->info("Skipped (no PO stock): {$skippedNoStock}");
        $this->info("Skipped (no move): {$skippedNoMove}");
        $this->info($apply ? 'DONE (applied).' : 'DONE (dry-run).');

        return Command::SUCCESS;
    }
}
