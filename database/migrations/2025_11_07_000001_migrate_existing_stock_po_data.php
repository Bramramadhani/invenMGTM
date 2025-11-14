<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Migrate existing stock data
        DB::table('stocks')
            ->whereNotNull('last_po_id')
            ->whereNull('purchase_order_id')
            ->chunkById(100, function ($stocks) {
                foreach ($stocks as $stock) {
                    DB::table('stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'purchase_order_id' => $stock->last_po_id
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No need for down migration as this is data migration
    }
};