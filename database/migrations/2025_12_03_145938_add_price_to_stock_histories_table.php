<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom harga ke tabel stock_histories
     */
    public function up(): void
    {
        Schema::table('stock_histories', function (Blueprint $table) {
            // harga per unit
            $table->decimal('unit_price', 16, 2)
                ->nullable()
                ->after('diff_quantity');

            // total = diff_quantity * unit_price
            $table->decimal('total_price', 18, 2)
                ->nullable()
                ->after('unit_price');
        });
    }

    /**
     * Rollback
     */
    public function down(): void
    {
        Schema::table('stock_histories', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'total_price']);
        });
    }
};
