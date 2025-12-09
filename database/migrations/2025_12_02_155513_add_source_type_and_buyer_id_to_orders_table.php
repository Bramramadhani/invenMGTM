<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom source_type & buyer_id ke tabel orders.
     *
     * source_type: 'po' (default) atau 'fob'
     * buyer_id   : relasi ke buyers (untuk order FOB)
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // tipe sumber stok: 'po' atau 'fob'
            $table->string('source_type', 20)
                ->default('po')
                ->after('status'); // kalau ragu dengan kolom 'status', boleh hilangkan ->after()

            // relasi ke buyers (untuk order FOB), boleh null
            $table->unsignedBigInteger('buyer_id')
                ->nullable()
                ->after('source_type');

            $table->foreign('buyer_id')
                ->references('id')
                ->on('buyers')
                ->onDelete('set null');
        });
    }

    /**
     * Rollback: hapus kolom dan foreign key.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // drop FK dulu baru kolom
            if (Schema::hasColumn('orders', 'buyer_id')) {
                $table->dropForeign(['buyer_id']);
            }

            if (Schema::hasColumn('orders', 'source_type')) {
                $table->dropColumn('source_type');
            }
            if (Schema::hasColumn('orders', 'buyer_id')) {
                $table->dropColumn('buyer_id');
            }
        });
    }
};
