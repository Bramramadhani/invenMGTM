<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;

class InventoryFixPo03 extends Command
{
    /**
     * Jalankan dengan:
     *   php artisan inventory:fix-po03
     */
    protected $signature = 'inventory:fix-po03';

    protected $description = 'Set ulang actual_arrived_quantity untuk PO nomor 03 berdasarkan data stok & pemakaian yang sudah ada.';

    public function handle(): int
    {
        $this->info('Mencari PO dengan nomor "03"...');

        $po = PurchaseOrder::where('po_number', '03')->first();

        if (!$po) {
            $this->error('PO dengan nomor "03" tidak ditemukan.');
            return Command::FAILURE;
        }

        $this->info("Ditemukan PO ID = {$po->id}");

        DB::transaction(function () use ($po) {
            // ======= ITEM SATU BARIS =======
            $mapSingles = [
                'CARELABEL'      => 10000,
                'HANGTAG'        => 9900,
                'LOGO'           => 5920,
                'METAL BEAD'     => 2630,
                'RING KOTAK'     => 6000,
                'RING O'         => 6000,
                'SILICA GEL'     => 13700,
                'TALI HANGTAG'   => 13700,
                // KEPALA RESLETING sengaja tidak diubah otomatis.
            ];

            foreach ($mapSingles as $name => $qty) {
                /** @var PurchaseOrderItem|null $item */
                $item = PurchaseOrderItem::where('purchase_order_id', $po->id)
                    ->where('material_name', $name)
                    ->where('unit', 'PCS')
                    ->first();

                if (!$item) {
                    $this->warn("Item '{$name}' (PCS) tidak ditemukan di PO {$po->po_number}, dilewati.");
                    continue;
                }

                $old = (float) $item->actual_arrived_quantity;
                $item->actual_arrived_quantity = $qty;
                $item->save();

                $this->info("Set {$name}: {$old} -> {$qty}");
            }

            // ======= MATERIAL DENGAN 2 BARIS: MAGNET =======
            $this->info('Memproses item MAGNET (2 baris)...');

            $magnetItems = PurchaseOrderItem::where('purchase_order_id', $po->id)
                ->where('material_name', 'MAGNET')
                ->where('unit', 'PCS')
                ->orderBy('id')
                ->get();

            if ($magnetItems->count() === 0) {
                $this->warn('Tidak ada item MAGNET ditemukan di PO ini.');
            } elseif ($magnetItems->count() === 1) {
                $this->warn('Hanya 1 baris MAGNET ditemukan, akan diset full ke 9960.');

                $item = $magnetItems->first();
                $old  = (float) $item->actual_arrived_quantity;
                $item->actual_arrived_quantity = 9960;
                $item->save();

                $this->info("Set MAGNET (ID {$item->id}): {$old} -> 9960");
            } else {
                $item1 = $magnetItems[0];
                $item2 = $magnetItems[1];

                $old1 = (float) $item1->actual_arrived_quantity;
                $old2 = (float) $item2->actual_arrived_quantity;

                $item1->actual_arrived_quantity = 3000;
                $item1->save();

                $item2->actual_arrived_quantity = 6960;
                $item2->save();

                $this->info("Set MAGNET (ID {$item1->id}): {$old1} -> 3000");
                $this->info("Set MAGNET (ID {$item2->id}): {$old2} -> 6960");

                if ($magnetItems->count() > 2) {
                    $this->warn('Ada lebih dari 2 baris MAGNET, baris ke-3 dst tidak diubah.');
                }
            }

            // ======= KEPALA RESLETING: INFO SAJA =======
            $kepala = PurchaseOrderItem::where('purchase_order_id', $po->id)
                ->where('material_name', 'KEPALA RESLETING')
                ->where('unit', 'PCS')
                ->orderBy('id')
                ->get();

            if ($kepala->count() > 0) {
                $this->warn('KEPALA RESLETING TIDAK diubah otomatis. Mohon cek manual DO / stok fisik.');
                foreach ($kepala as $it) {
                    $this->line("  - ID {$it->id}, ordered = {$it->ordered_quantity}, actual_arrived = {$it->actual_arrived_quantity}");
                }
            } else {
                $this->info('Tidak ada item KEPALA RESLETING di PO ini.');
            }
        });

        $this->info('Selesai set actual_arrived_quantity untuk PO 03.');
        $this->info('Silakan cek lagi halaman Detail PO 03 di aplikasi (Ringkasan Item PO).');

        return Command::SUCCESS;
    }
}
