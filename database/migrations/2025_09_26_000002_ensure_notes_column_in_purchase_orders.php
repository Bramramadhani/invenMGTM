<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('purchase_orders', 'notes')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('po_number');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('purchase_orders', 'notes')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};