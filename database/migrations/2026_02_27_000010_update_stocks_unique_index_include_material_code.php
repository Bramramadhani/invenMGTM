<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $v2 = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'stocks'
              AND INDEX_NAME = 'stocks_unique_sup_mat_unit_po_v2'
        ");

        if (($v2->c ?? 0) > 0) {
            DB::statement("ALTER TABLE `stocks` DROP INDEX `stocks_unique_sup_mat_unit_po_v2`");
        }

        $v3 = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'stocks'
              AND INDEX_NAME = 'stocks_unique_sup_mat_code_unit_po_v3'
        ");

        if (($v3->c ?? 0) === 0) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->unique(
                    ['supplier_id', 'material_name', 'material_code', 'unit', 'purchase_order_id'],
                    'stocks_unique_sup_mat_code_unit_po_v3'
                );
            });
        }
    }

    public function down(): void
    {
        $v3 = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'stocks'
              AND INDEX_NAME = 'stocks_unique_sup_mat_code_unit_po_v3'
        ");

        if (($v3->c ?? 0) > 0) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropUnique('stocks_unique_sup_mat_code_unit_po_v3');
            });
        }

        $v2 = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'stocks'
              AND INDEX_NAME = 'stocks_unique_sup_mat_unit_po_v2'
        ");

        if (($v2->c ?? 0) === 0) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->unique(
                    ['supplier_id', 'material_name', 'unit', 'purchase_order_id'],
                    'stocks_unique_sup_mat_unit_po_v2'
                );
            });
        }
    }
};
