<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop unique lama jika ada (berbasis last_po_id)
        $idx = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'stocks'
              AND INDEX_NAME = 'stocks_unique_sup_mat_unit_po'
        ");
        if (($idx->c ?? 0) > 0) {
            DB::statement("ALTER TABLE `stocks` DROP INDEX `stocks_unique_sup_mat_unit_po`");
        }

        // Buat unique baru berbasis purchase_order_id
        Schema::table('stocks', function (Blueprint $t) {
            $t->unique(
                ['supplier_id', 'material_name', 'unit', 'purchase_order_id'],
                'stocks_unique_sup_mat_unit_po_v2'
            );
        });

        // (Opsional) drop index yang hanya pada last_po_id jika memang ada
        $idx2 = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'stocks'
              AND INDEX_NAME = 'stocks_last_po_id_index'
        ");
        if (($idx2->c ?? 0) > 0) {
            DB::statement("ALTER TABLE `stocks` DROP INDEX `stocks_last_po_id_index`");
        }
    }

    public function down(): void
    {
        // Balikkan: hapus unique baru dan kembalikan unique lama (berbasis last_po_id)
        Schema::table('stocks', function (Blueprint $t) {
            $t->dropUnique('stocks_unique_sup_mat_unit_po_v2');
        });

        DB::statement("
            ALTER TABLE `stocks`
            ADD UNIQUE KEY `stocks_unique_sup_mat_unit_po`
            (`supplier_id`,`material_name`,`unit`,`last_po_id`)
        ");
    }
};
