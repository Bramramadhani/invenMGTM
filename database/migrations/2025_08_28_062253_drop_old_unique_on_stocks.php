<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropUnique('stocks_supplier_material_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->unique(
                ['supplier_id','material_name','unit'],
                'stocks_supplier_material_unit_unique'
            );
        });
    }
};
