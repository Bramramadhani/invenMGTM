<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            // Skip product & category FK as those tables may not exist
            // $table->foreignId('product_id')->constrained()->onDelete('cascade');
            // $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('unit')->nullable(); // contoh: pcs, roll, dll
            $table->integer('ordered_quantity')->nullable(); // jumlah dipesan
            $table->integer('actual_arrived_quantity')->default(0); // jumlah aktual datang
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
