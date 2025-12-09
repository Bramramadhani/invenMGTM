<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Drop foreign key lama ke suppliers (kalau ada)
        Schema::table('stocks', function (Blueprint $table) {
            // Nama default FK: stocks_supplier_id_foreign
            // dropForeign(['supplier_id']) akan handle nama default tsb
            $table->dropForeign(['supplier_id']);
        });

        // 2) Ubah kolom supplier_id jadi boleh NULL
        //    (MySQL / MariaDB syntax)
        DB::statement('ALTER TABLE stocks MODIFY supplier_id BIGINT UNSIGNED NULL');

        // 3) Re-add foreign key dengan ON DELETE SET NULL (optional, tapi rapi)
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete(); // kalau supplier dihapus, supplier_id di stok jadi NULL
        });
    }

    public function down(): void
    {
        // Kembalikan ke NOT NULL kalau di-rollback

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        DB::statement('ALTER TABLE stocks MODIFY supplier_id BIGINT UNSIGNED NOT NULL');

        Schema::table('stocks', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->cascadeOnDelete(); // sesuaikan kalau sebelumnya beda
        });
    }
};
