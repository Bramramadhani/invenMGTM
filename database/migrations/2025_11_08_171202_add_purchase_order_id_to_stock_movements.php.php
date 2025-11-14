<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $t) {
            $t->unsignedBigInteger('purchase_order_id')->nullable()->after('ref_id');
            $t->index('purchase_order_id', 'sm_purchase_order_id_idx');
            // Jika ingin FK aktif sekarang, pastikan data sudah bersih dulu:
            // $t->foreign('purchase_order_id')->references('id')->on('purchase_orders');
        });

        // Backfill dari stocks
        DB::statement("
            UPDATE stock_movements sm
            JOIN stocks s ON s.id = sm.stock_id
            SET sm.purchase_order_id = s.purchase_order_id
            WHERE sm.purchase_order_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $t) {
            // $t->dropForeign(['purchase_order_id']);
            $t->dropIndex('sm_purchase_order_id_idx');
            $t->dropColumn('purchase_order_id');
        });
    }
};
