<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            // Hapus unique lama (global) di receipt_number
            // Nama index lama terlihat di error: purchase_receipts_receipt_number_unique
            $table->dropUnique('purchase_receipts_receipt_number_unique');

            // Buat unique baru per-PO
            $table->unique(['purchase_order_id', 'receipt_number'], 'pr_unique_receipt_per_po');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            // Kembalikan ke unique global jika di-rollback
            $table->dropUnique('pr_unique_receipt_per_po');
            $table->unique('receipt_number', 'purchase_receipts_receipt_number_unique');
        });
    }
};
