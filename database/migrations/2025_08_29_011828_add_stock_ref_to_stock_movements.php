<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('stock_movements')) {
            // Tabel belum ada -> lewati (akan dibuat oleh create_stock_movements_table)
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'stock_id')) {
                $table->unsignedBigInteger('stock_id')->nullable()->after('supplier_id');
                $table->index('stock_id', 'sm_stock_id_idx');
                $table->foreign('stock_id')->references('id')->on('stocks')
                      ->cascadeOnUpdate()->restrictOnDelete();
            }
            if (!Schema::hasColumn('stock_movements', 'po_number')) {
                $table->string('po_number', 50)->nullable()->after('stock_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stock_movements')) return;

        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'stock_id')) {
                $table->dropForeign(['stock_id']);
                $table->dropIndex('sm_stock_id_idx');
                $table->dropColumn('stock_id');
            }
            if (Schema::hasColumn('stock_movements', 'po_number')) {
                $table->dropColumn('po_number');
            }
        });
    }
};
