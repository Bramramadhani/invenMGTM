<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('production_issues', function (Blueprint $t) {
      $t->id();
      $t->date('issue_date');
      $t->string('issue_number')->unique();
      $t->text('notes')->nullable();
      $t->enum('status', ['draft','posted','void'])->default('draft');
      $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
      $t->timestamp('posted_at')->nullable();
      $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
      $t->unsignedBigInteger('order_id')->nullable();       // link ke permintaan (Order) — opsional
      $t->timestamps();
      $t->index(['issue_date','status']);
      $t->index('order_id','pi_order_id_index');
    });

    Schema::create('production_issue_items', function (Blueprint $t) {
      $t->id();
      $t->foreignId('production_issue_id')->constrained('production_issues')->cascadeOnDelete();
      $t->unsignedBigInteger('order_item_id')->nullable();  // link ke baris permintaan — opsional
      $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnUpdate()->restrictOnDelete();
      $t->string('material_name');
      $t->string('unit')->nullable();
      $t->decimal('quantity', 18, 4);
      $t->text('notes')->nullable();
      $t->timestamps();
      $t->index(['supplier_id','material_name','unit']);
      $t->index('order_item_id','pii_order_item_id_index');
    });
  }

  public function down(): void {
    Schema::dropIfExists('production_issue_items');
    Schema::dropIfExists('production_issues');
  }
};
