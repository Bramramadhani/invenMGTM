<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Drop SEMUA unique index yang hanya di kolom po_number (nama bisa beda-beda)
        $indexes = DB::select("
            SELECT INDEX_NAME, NON_UNIQUE
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'purchase_orders'
              AND COLUMN_NAME = 'po_number'
        ");

        foreach ($indexes as $ix) {
            // NON_UNIQUE = 0 artinya UNIQUE
            if ((int)$ix->NON_UNIQUE === 0 && $ix->INDEX_NAME !== 'PRIMARY') {
                DB::statement("ALTER TABLE `purchase_orders` DROP INDEX `{$ix->INDEX_NAME}`");
            }
        }

        // 2) Tambah UNIQUE per supplier (supplier_id + po_number) jika belum ada
        $exists = DB::selectOne("
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'purchase_orders'
              AND INDEX_NAME = 'po_unique_per_supplier'
            LIMIT 1
        ");

        if (!$exists) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->unique(['supplier_id', 'po_number'], 'po_unique_per_supplier');
            });
        }
    }

    public function down(): void
    {
        // 1) Drop unique gabungan jika ada
        try {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropUnique('po_unique_per_supplier');
            });
        } catch (\Throwable $e) {
            // abaikan jika sudah tidak ada
        }

        // 2) (Opsional) kembalikan unique global di po_number kalau perlu
        // Cek dulu apakah sudah ada unique di po_number
        $hasUnique = DB::selectOne("
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'purchase_orders'
              AND COLUMN_NAME = 'po_number'
              AND NON_UNIQUE = 0
            LIMIT 1
        ");

        if (!$hasUnique) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                // Laravel akan beri nama otomatis (purchase_orders_po_number_unique)
                $table->unique('po_number');
            });
        }
    }
};
