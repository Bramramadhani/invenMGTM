<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_correction_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_receipt_id');
            $table->unsignedBigInteger('purchase_receipt_item_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            $table->unsignedBigInteger('stock_id')->nullable();

            $table->string('material_name');
            $table->string('material_code', 64)->nullable();
            $table->string('unit', 50)->nullable();

            $table->decimal('old_received_qty', 18, 4);
            $table->decimal('new_received_qty', 18, 4);
            $table->decimal('delta_received_qty', 18, 4);
            $table->decimal('stock_old_qty', 18, 4)->nullable();
            $table->decimal('stock_new_qty', 18, 4)->nullable();

            $table->boolean('is_forced')->default(false);
            $table->string('reason', 500)->nullable();
            $table->string('force_reason', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['purchase_receipt_id', 'created_at'], 'rca_receipt_created_idx');
            $table->index(['is_forced', 'created_at'], 'rca_forced_created_idx');
            $table->index(['purchase_order_id'], 'rca_po_idx');

            $table->foreign('purchase_receipt_id', 'rca_receipt_fk')
                ->references('id')
                ->on('purchase_receipts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('purchase_receipt_item_id', 'rca_receipt_item_fk')
                ->references('id')
                ->on('purchase_receipt_items')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_correction_audits');
    }
};

