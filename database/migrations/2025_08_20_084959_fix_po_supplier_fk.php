<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $t) {
            // buang FK lama kalau ada (abaikan error jika belum ada)
            try { $t->dropForeign(['supplier_id']); } catch (\Throwable $e) {}

            // pasang FK baru: RESTRICT delete (aman), CASCADE update
            $t->foreign('supplier_id')
              ->references('id')->on('suppliers')
              ->restrictOnDelete()
              ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $t) {
            try { $t->dropForeign(['supplier_id']); } catch (\Throwable $e) {}
            // kembalikan FK sederhana (opsional)
            $t->foreign('supplier_id')
              ->references('id')->on('suppliers');
        });
    }
};
