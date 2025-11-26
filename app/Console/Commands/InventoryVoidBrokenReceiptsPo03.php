<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;

class InventoryVoidBrokenReceiptsPo03 extends Command
{
    /**
     * Jalankan dengan:
     *   php artisan inventory:void-broken-receipts-po03
     */
    protected $signature = 'inventory:void-broken-receipts-po03';

    protected $description = 'AUTO-VOID semua purchase receipt POSTED yang tidak punya item (0 item) untuk PO nomor 03 (bug lama).';

    public function handle(): int
    {
        $this->info('Mencari PO dengan nomor "03"...');

        $po = PurchaseOrder::where('po_number', '03')->first();

        if (!$po) {
            $this->error('PO dengan nomor "03" tidak ditemukan.');
            return Command::FAILURE;
        }

        $this->info("PO ditemukan. ID = {$po->id}");

        DB::transaction(function () use ($po) {
            // Receipt POSTED milik PO ini yang sama sekali tidak punya item
            $receipts = PurchaseReceipt::where('purchase_order_id', $po->id)
                ->where('status', PurchaseReceipt::STATUS_POSTED)
                ->whereDoesntHave('items')
                ->get();

            if ($receipts->isEmpty()) {
                $this->info('Tidak ada receipt POSTED yang kosong untuk PO ini.');
                return;
            }

            $this->warn('Receipt berikut akan di-VOID (0 item / total 0):');

            foreach ($receipts as $rc) {
                $this->line("- {$rc->receipt_number} (ID {$rc->id})");

                $rc->status    = PurchaseReceipt::STATUS_VOID;
                $rc->voided_at = now();

                if ($rc->notes) {
                    $rc->notes .= "\n[auto-void] Receipt kosong akibat bug edit PO lama.";
                } else {
                    $rc->notes = "[auto-void] Receipt kosong akibat bug edit PO lama.";
                }

                // voided_by dibiarkan null (CLI, bukan user login)
                $rc->save();
            }

            $this->info('Selesai: semua receipt kosong berhasil di-VOID.');
        });

        return Command::SUCCESS;
    }
}
