<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            // buyer_id nullable, karena stok biasa tetap pakai supplier_id
            $table->unsignedBigInteger('buyer_id')
                ->nullable()
                ->after('supplier_id');

            $table->foreign('buyer_id')
                ->references('id')
                ->on('buyers')
                ->nullOnDelete(); // kalau buyer dihapus, buyer_id di stok jadi NULL
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
            $table->dropColumn('buyer_id');
        });
    }
};
