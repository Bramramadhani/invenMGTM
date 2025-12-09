<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * This migration was part of legacy product-based flow.
     * Since system evolved to use only Suppliers->PO->Receipt->Stock->FOB flows,
     * this migration is mostly obsolete. Keeping for historical record but minimal operations.
     */
    public function up()
    {
        // Skip: product_id FK cleanup not needed for current FOB-focused flow
        // All product-related columns removed in earlier migrations anyway
    }

    public function down()
    {
        // Kalau di-rollback, kita buat ulang struktur dasar tabel
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Tambahkan kembali kolom product_id di carts
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
        });

        // Tambahkan kembali kolom product_id & category_id di purchase_order_items
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
        });
    }
};
