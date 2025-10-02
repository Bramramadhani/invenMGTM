<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('purchase_receipts', function (Blueprint $t) {
      $t->id();
      $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
      $t->date('receipt_date');
      $t->string('receipt_number')->unique(); // contoh: RC-PO121-001
      $t->text('notes')->nullable();
      $t->timestamps();
    });
  }

  public function down(): void {
    Schema::dropIfExists('purchase_receipts');
  }
};
