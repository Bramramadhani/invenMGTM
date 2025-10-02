<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $t) {
            $t->id();

            $t->foreignId('supplier_id')
              ->constrained('suppliers')
              ->cascadeOnUpdate()
              ->restrictOnDelete(); // ganti ke ->nullOnDelete()->nullable() jika mau

            $t->string('material_name');
            $t->string('unit')->nullable();
            $t->decimal('quantity', 18, 4)->default(0);
            $t->timestamps();

            // index & unique
            $t->index('supplier_id', 'stocks_supplier_id_index');
            $t->index('material_name', 'stocks_material_name_index');
            $t->unique(['supplier_id','material_name','unit'], 'stocks_supplier_material_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
