<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Nama FK baru (biar konsisten & mudah di-drop nanti)
    private string $fkName = 'fk_purchase_orders_supplier_id';

    public function up(): void
    {

        $schema   = DB::getDatabaseName();
        $currentFks = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', 'purchase_orders')
            ->where('COLUMN_NAME', 'supplier_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')     // hanya FK, bukan index biasa
            ->pluck('CONSTRAINT_NAME')
            ->all();

        foreach ($currentFks as $name) {
            Schema::table('purchase_orders', function (Blueprint $t) use ($name) {
                try { $t->dropForeign($name); } catch (\Throwable $e) {}
            });
        }

        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->foreign('supplier_id', $this->fkName)
              ->references('id')->on('suppliers')
              ->restrictOnDelete()    // tidak boleh hapus supplier jika masih dipakai PO
              ->cascadeOnUpdate();    // aman kalau id berubah (jarang)
        });
    }

    public function down(): void
    {
    
        Schema::table('purchase_orders', function (Blueprint $t) {
            try { $t->dropForeign($this->fkName); } catch (\Throwable $e) {}
        });

        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->foreign('supplier_id')->references('id')->on('suppliers');
        });
    }
};
