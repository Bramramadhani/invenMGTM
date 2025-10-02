<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // referensi kuat ke baris stok per-PO (boleh nullable untuk data lama)
            $table->unsignedBigInteger('stock_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();

            $table->string('material_name');
            $table->string('unit', 50)->nullable();

            // IN | OUT | ADJ
            $table->enum('direction', ['IN','OUT','ADJ']);

            // sesuaikan presisi dg sistemmu (sebelumnya pakai 4 desimal)
            $table->decimal('quantity', 18, 4);

            $table->text('notes')->nullable();

            // referensi dokumen (optional)
            $table->string('ref_type', 50)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();

            // snapshot no PO (opsional, memudahkan laporan tanpa join)
            $table->string('po_number', 50)->nullable();

            $table->timestamp('moved_at')->nullable();
            $table->timestamps();

            // index
            $table->index('stock_id', 'sm_stock_id_idx');
            $table->index('supplier_id');
            $table->index(['direction', 'moved_at']);

            // FK (optional tapi disarankan)
            $table->foreign('stock_id')->references('id')->on('stocks')
                  ->cascadeOnUpdate()->restrictOnDelete();
            // Jika mau, aktifkan juga FK ke suppliers:
            // $table->foreign('supplier_id')->references('id')->on('suppliers')
            //       ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
