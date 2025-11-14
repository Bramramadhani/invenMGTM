<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pastikan tabel ada
        if (!Schema::hasTable('stock_movements')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            // Tambah kolom order_id bila belum ada
            if (!Schema::hasColumn('stock_movements', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('notes');
                $table->index('order_id', 'sm_order_id_idx');

                // (Opsional) FK, aktifkan hanya jika mau:
                // $table->foreign('order_id')
                //       ->references('id')->on('orders')
                //       ->nullOnDelete()->cascadeOnUpdate();
            }

            // Tambah kolom order_item_id bila belum ada
            if (!Schema::hasColumn('stock_movements', 'order_item_id')) {
                $table->unsignedBigInteger('order_item_id')->nullable()->after('order_id');
                $table->index('order_item_id', 'sm_order_item_id_idx');

                // (Opsional) FK, aktifkan hanya jika mau:
                // $table->foreign('order_item_id')
                //       ->references('id')->on('order_items')
                //       ->nullOnDelete()->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stock_movements')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            // Kalau sebelumnya mengaktifkan FK, drop dulu (comment out jika tidak pakai FK)
            // if (Schema::hasColumn('stock_movements', 'order_id')) {
            //     $table->dropForeign(['order_id']);
            // }
            // if (Schema::hasColumn('stock_movements', 'order_item_id')) {
            //     $table->dropForeign(['order_item_id']);
            // }

            if (Schema::hasColumn('stock_movements', 'order_item_id')) {
                // drop index jika ada
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
