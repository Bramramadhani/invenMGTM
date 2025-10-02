<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_issue_items', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_id')->nullable()->after('order_item_id');
            $table->index('stock_id', 'pi_items_stock_id_idx');
            $table->foreign('stock_id')->references('id')->on('stocks')->cascadeOnUpdate()->restrictOnDelete();
        });

        // Backfill dari order_items jika ada keterkaitan
        try {
            DB::statement("
                UPDATE production_issue_items pii
                JOIN order_items oi ON oi.id = pii.order_item_id
                SET pii.stock_id = oi.stock_id
                WHERE pii.stock_id IS NULL AND oi.stock_id IS NOT NULL
            ");
        } catch (\Throwable $e) {
            
        }

    }

    public function down(): void
    {
        Schema::table('production_issue_items', function (Blueprint $table) {
            $table->dropForeign(['stock_id']);
            $table->dropIndex('pi_items_stock_id_idx');
            $table->dropColumn('stock_id');
        });
    }
};
