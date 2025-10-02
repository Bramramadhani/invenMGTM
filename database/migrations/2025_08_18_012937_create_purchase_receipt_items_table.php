<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('purchase_receipt_items', function (Blueprint $t) {
      $t->id();
      $t->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
      $t->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();

      // salin identitas material (tanpa master)
      $t->foreignId('supplier_id')->constrained('suppliers');
      $t->string('material_name');
      $t->string('unit')->nullable();

      $t->decimal('received_quantity', 18, 4);
      $t->decimal('unit_price', 18, 2)->nullable(); // opsional
      $t->text('notes')->nullable();
      $t->timestamps();

      $t->index(['purchase_order_item_id']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('purchase_receipt_items');
  }
};
