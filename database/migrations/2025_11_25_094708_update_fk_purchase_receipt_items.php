<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            // Hapus foreign key lama
            $table->dropForeign(['purchase_order_item_id']);

            // Ganti: tidak boleh hapus purchase_order_items yang sudah dipakai receipt
            $table->foreign('purchase_order_item_id')
                ->references('id')
                ->on('purchase_order_items')
                ->restrictOnDelete(); // atau ->nullOnDelete() kalau kolom mau dibuat nullable
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_item_id']);

            // Kembalikan ke kondisi awal (cascade)
            $table->foreign('purchase_order_item_id')
                ->references('id')
                ->on('purchase_order_items')
                ->cascadeOnDelete();
        });
    }
};
