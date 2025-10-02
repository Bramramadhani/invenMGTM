<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // STOK
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('material_code', 64)->nullable()->after('material_name');
            $table->index('material_code', 'idx_stocks_material_code');
        });

        // ITEM PO
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('material_code', 64)->nullable()->after('material_name');
            $table->index('material_code', 'idx_poi_material_code');
        });

        // ITEM PERMINTAAN
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('material_code', 64)->nullable()->after('material_name');
            $table->index('material_code', 'idx_order_items_material_code');
        });

        // ITEM BARANG KELUAR
        Schema::table('production_issue_items', function (Blueprint $table) {
            $table->string('material_code', 64)->nullable()->after('material_name');
            $table->index('material_code', 'idx_pii_material_code');
        });
    }

    public function down(): void
    {
        Schema::table('production_issue_items', function (Blueprint $table) {
            $table->dropIndex('idx_pii_material_code');
            $table->dropColumn('material_code');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('idx_order_items_material_code');
            $table->dropColumn('material_code');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex('idx_poi_material_code');
            $table->dropColumn('material_code');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex('idx_stocks_material_code');
            $table->dropColumn('material_code');
        });
    }
};
