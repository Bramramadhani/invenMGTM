<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('last_po_id')->nullable()->after('supplier_id');
            $table->string('last_po_number', 50)->nullable()->after('last_po_id');
            // index kecil agar pencarian cepat (opsional)
            $table->index('last_po_id');
        });
    }

    public function down(): void {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex(['last_po_id']);
            $table->dropColumn(['last_po_id', 'last_po_number']);
        });
    }
};
