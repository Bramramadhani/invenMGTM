<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Hapus foreign key di carts
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
        });

        // Hapus foreign key di purchase_order_items
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
            if (Schema::hasColumn('purchase_order_items', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
        });

        // Drop tabel products & categories jika ada
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::enableForeignKeyConstraints();
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
