<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('stock_movements')) return;

        Schema::table('stock_movements', function (Blueprint $table) {
            // Tambah kolom TANPA AFTER (biar nggak tergantung urutan/kolom lain)
            if (!Schema::hasColumn('stock_movements', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable();
                $table->index('order_id', 'sm_order_id_idx');
            }
            if (!Schema::hasColumn('stock_movements', 'order_item_id')) {
                $table->unsignedBigInteger('order_item_id')->nullable();
                $table->index('order_item_id', 'sm_order_item_id_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stock_movements')) return;

        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'order_item_id')) {
                // dropIndex terima nama index, jadi gunakan nama yang kita buat
                try { $table->dropIndex('sm_order_item_id_idx'); } catch (\Throwable $e) {}
                $table->dropColumn('order_item_id');
            }
            if (Schema::hasColumn('stock_movements', 'order_id')) {
                try { $table->dropIndex('sm_order_id_idx'); } catch (\Throwable $e) {}
                $table->dropColumn('order_id');
            }
        });
    }
};
