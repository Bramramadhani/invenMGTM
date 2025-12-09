<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * ORDER ITEMS: supplier_id jadi nullable + FK ON DELETE SET NULL
         */
        Schema::table('order_items', function (Blueprint $table) {
            // hapus FK lama dulu
            $table->dropForeign(['supplier_id']);
        });

        // ubah kolom jadi NULLABLE (tanpa doctrine/dbal, pakai raw SQL)
        DB::statement('ALTER TABLE `order_items` MODIFY `supplier_id` BIGINT UNSIGNED NULL;');

        // buat FK baru dengan nullOnDelete
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();
        });

        /*
         * PRODUCTION ISSUE ITEMS: supplier_id juga jadi nullable
         */
        Schema::table('production_issue_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        DB::statement('ALTER TABLE `production_issue_items` MODIFY `supplier_id` BIGINT UNSIGNED NULL;');

        Schema::table('production_issue_items', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // ROLLBACK: balikin ke NOT NULL + FK biasa

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        DB::statement('ALTER TABLE `order_items` MODIFY `supplier_id` BIGINT UNSIGNED NOT NULL;');

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers');
        });

        Schema::table('production_issue_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        DB::statement('ALTER TABLE `production_issue_items` MODIFY `supplier_id` BIGINT UNSIGNED NOT NULL;');

        Schema::table('production_issue_items', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers');
        });
    }
};
