<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Tambahkan kolom baru hanya jika belum ada
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

            // Hapus kolom product_id jika ada, setelah foreign key-nya dilepas
            if (Schema::hasColumn('purchase_orders', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }

            // Hapus kolom quantity jika ada
            if (Schema::hasColumn('purchase_orders', 'quantity')) {
                $table->dropColumn('quantity');
            }

            // Hapus kolom date jika ada
            if (Schema::hasColumn('purchase_orders', 'date')) {
                $table->dropColumn('date');
            }
        });
    }

    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('product_id')->nullable();
            $table->integer('quantity')->nullable();
            $table->date('date')->nullable();
            $table->dropColumn(['po_number', 'arrival_date', 'target_completion_date', 'is_completed']);
        });
    }
};
