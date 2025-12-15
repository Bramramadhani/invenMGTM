<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('vendor_name', 191)
                  ->nullable()
                  ->after('buyer_id'); // hanya dipakai stok FOB
        });
    }

    public function down(): void
    {
        Schema::table('stocks', fn (Blueprint $table) => $table->dropColumn('vendor_name'));
    }
};
