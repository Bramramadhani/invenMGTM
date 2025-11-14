<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_id')
                  ->nullable()
                  ->after('supplier_id');

            // Add index for better query performance
            $table->index('purchase_order_id', 'stocks_po_id_idx');
            
            // Add foreign key constraint for referential integrity
            $table->foreign('purchase_order_id', 'stocks_purchase_order_fk')
                  ->references('id')
                  ->on('purchase_orders')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign('stocks_purchase_order_fk');
            $table->dropIndex('stocks_po_id_idx');
            $table->dropColumn('purchase_order_id');
        });
    }
};