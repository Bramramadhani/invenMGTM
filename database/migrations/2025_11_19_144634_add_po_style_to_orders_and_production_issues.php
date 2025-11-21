<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('purchase_order_style_id')
                ->nullable()
                ->after('id')
                ->constrained('purchase_order_styles')
                ->nullOnDelete();
        });

        Schema::table('production_issues', function (Blueprint $table) {
            $table->foreignId('purchase_order_style_id')
                ->nullable()
                ->after('order_id')
                ->constrained('purchase_order_styles')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_style_id']);
            $table->dropColumn('purchase_order_style_id');
        });

        Schema::table('production_issues', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_style_id']);
            $table->dropColumn('purchase_order_style_id');
        });
    }
};
