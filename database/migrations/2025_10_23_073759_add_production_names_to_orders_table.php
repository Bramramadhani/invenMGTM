<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('production_name', 150)
                  ->nullable()
                  ->after('notes')
                  ->comment('Nama produksi yang meminta barang');

            $table->string('warehouse_admin_name', 150)
                  ->nullable()
                  ->after('production_name')
                  ->comment('Nama admin gudang yang menyerahkan barang');

            $table->string('warehouse_leader_name', 150)
                  ->nullable()
                  ->after('warehouse_admin_name')
                  ->comment('Nama leader gudang');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'production_name',
                'warehouse_admin_name',
                'warehouse_leader_name',
            ]);
        });
    }
};
