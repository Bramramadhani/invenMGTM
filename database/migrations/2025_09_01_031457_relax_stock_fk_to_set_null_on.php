<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('production_issue_items') && Schema::hasColumn('production_issue_items', 'stock_id')) {
            // Drop FK lama (nama default laravel: production_issue_items_stock_id_foreign)
            Schema::table('production_issue_items', function (Blueprint $table) {
                $table->dropForeign(['stock_id']);
            });

            // Ubah kolom jadi NULLABLE (tanpa doctrine/dbal)
            DB::statement('ALTER TABLE production_issue_items MODIFY stock_id BIGINT UNSIGNED NULL');

            // Pasang FK baru: ON DELETE SET NULL, ON UPDATE CASCADE
            Schema::table('production_issue_items', function (Blueprint $table) {
                $table->foreign('stock_id')
                    ->references('id')->on('stocks')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'stock_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['stock_id']);
            });

            DB::statement('ALTER TABLE order_items MODIFY stock_id BIGINT UNSIGNED NULL');

            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('stock_id')
                    ->references('id')->on('stocks')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }


        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'stock_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropForeign(['stock_id']);
            });

            DB::statement('ALTER TABLE stock_movements MODIFY stock_id BIGINT UNSIGNED NULL');

            Schema::table('stock_movements', function (Blueprint $table) {
                $table->foreign('stock_id')
                    ->references('id')->on('stocks')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // Kembalikan ke RESTRICT + NOT NULL (PERHATIAN: gagal jika ada nilai NULL di stock_id)
        if (Schema::hasTable('production_issue_items') && Schema::hasColumn('production_issue_items', 'stock_id')) {
            Schema::table('production_issue_items', function (Blueprint $table) {
                $table->dropForeign(['stock_id']);
            });
            DB::statement('UPDATE production_issue_items SET stock_id = 0 WHERE stock_id IS NULL'); // opsional, agar NOT NULL tidak gagal
            DB::statement('ALTER TABLE production_issue_items MODIFY stock_id BIGINT UNSIGNED NOT NULL');

            Schema::table('production_issue_items', function (Blueprint $table) {
                $table->foreign('stock_id')
                    ->references('id')->on('stocks')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'stock_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['stock_id']);
            });
            DB::statement('UPDATE order_items SET stock_id = 0 WHERE stock_id IS NULL'); // opsional
            DB::statement('ALTER TABLE order_items MODIFY stock_id BIGINT UNSIGNED NOT NULL');

            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('stock_id')
                    ->references('id')->on('stocks')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'stock_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropForeign(['stock_id']);
            });
            DB::statement('UPDATE stock_movements SET stock_id = 0 WHERE stock_id IS NULL'); // opsional
            DB::statement('ALTER TABLE stock_movements MODIFY stock_id BIGINT UNSIGNED NOT NULL');

            Schema::table('stock_movements', function (Blueprint $table) {
                $table->foreign('stock_id')
                    ->references('id')->on('stocks')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }
};
