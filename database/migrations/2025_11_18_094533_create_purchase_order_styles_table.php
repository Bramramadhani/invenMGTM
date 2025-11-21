<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_order_styles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                  ->constrained()
                  ->onDelete('cascade');

            $table->string('style_name', 100);
            $table->integer('style_quantity')->default(0); // jumlah tas per style

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_styles');
    }
};
