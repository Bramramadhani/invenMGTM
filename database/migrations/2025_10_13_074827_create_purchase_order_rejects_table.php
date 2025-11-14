<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_order_rejects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('material_name');
            $table->string('unit', 50)->nullable();
            $table->decimal('reject_quantity', 12, 4)->default(0);
            $table->text('previous_notes')->nullable(); // Catatan dari penerimaan parsial sebelumnya
            $table->text('new_notes')->nullable();      // Catatan baru checker (alasan reject)
            $table->string('reason')->nullable();       // Kode atau ringkasan alasan (opsional)
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['purchase_order_id']);
            $table->index(['purchase_order_item_id']);
            $table->index(['stock_id']);
            $table->index(['supplier_id']);
            $table->index(['created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_rejects');
    }
};
