<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ====== UPDATE TABLE purchase_orders ======
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Hapus foreign key & kolom product_id kalau ada
            if (Schema::hasColumn('purchase_orders', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }

            // Tambah kolom baru hanya jika belum ada
            if (!Schema::hasColumn('purchase_orders', 'po_number')) {
                $table->string('po_number')->nullable()->after('id');
            }
            if (!Schema::hasColumn('purchase_orders', 'arrival_date')) {
                $table->date('arrival_date')->nullable()->after('supplier_id');
            }
            if (!Schema::hasColumn('purchase_orders', 'target_completion_date')) {
                $table->date('target_completion_date')->nullable()->after('arrival_date');
            }
            if (!Schema::hasColumn('purchase_orders', 'is_completed')) {
                $table->boolean('is_completed')->default(false)->after('target_completion_date');
            }
        });

        // ====== UPDATE TABLE purchase_order_items ======
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_items', 'actual_arrived_quantity')) {
                $table->integer('actual_arrived_quantity')->default(0)->after('ordered_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable();
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            }

            if (Schema::hasColumn('purchase_orders', 'po_number')) {
                $table->dropColumn('po_number');
            }
            if (Schema::hasColumn('purchase_orders', 'arrival_date')) {
                $table->dropColumn('arrival_date');
            }
            if (Schema::hasColumn('purchase_orders', 'target_completion_date')) {
                $table->dropColumn('target_completion_date');
            }
            if (Schema::hasColumn('purchase_orders', 'is_completed')) {
                $table->dropColumn('is_completed');
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'actual_arrived_quantity')) {
                $table->dropColumn('actual_arrived_quantity');
            }
        });
    }
};
