<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')
                ->constrained()
                ->onDelete('cascade');

            $table->unsignedBigInteger('purchase_order_id')->nullable();

            $table->string('type', 50); // manual_edit, manual_delete, receipt_post, issue_out, dll.
            $table->decimal('old_quantity', 14, 4);
            $table->decimal('new_quantity', 14, 4);
            $table->decimal('diff_quantity', 14, 4);

            $table->string('material_name');
            $table->string('material_code', 64)->nullable();
            $table->string('unit', 50)->nullable();

            $table->string('reason', 255)->nullable(); // alasan singkat

            $table->unsignedBigInteger('created_by')->nullable(); // user id

            $table->timestamps();

            // index tambahan
            $table->index(['stock_id', 'created_at']);
            $table->index(['purchase_order_id']);
            $table->index(['type']);

            // FK optional ke purchase_orders (tanpa constraint keras supaya aman)
            // Kalau mau pakai:
            // $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_histories');
    }
};
