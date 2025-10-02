<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * 1) Tambahkan kolom notes ke tabel orders (jika belum ada)
         */
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'notes')) {
                    $table->string('notes', 500)->nullable()->after('status');
                }
            });
        }

        /**
         * 2) Pastikan tabel order_items ada.
         *    - Jika BELUM ada: buat tabel lengkap (sesuai yang dipakai controller/model).
         *    - Jika SUDAH ada: tambahkan kolom yang kurang (stock_id, notes).
         */
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('stock_id')->nullable();     // referensi baris stok per-PO
                $table->unsignedBigInteger('supplier_id')->nullable();  // snapshot supplier
                $table->string('material_name');
                $table->string('unit', 50)->nullable();
                $table->decimal('quantity', 18, 4);
                $table->string('notes', 255)->nullable();

                $table->timestamps();

                // Indexes
                $table->index('order_id', 'oi_order_id_idx');
                $table->index('stock_id', 'oi_stock_id_idx');
                $table->index('supplier_id', 'oi_supplier_id_idx');

                // Foreign keys (gunakan set null pada stock/supplier agar aman jika dihapus)
                $table->foreign('order_id')
                    ->references('id')->on('orders')
                    ->cascadeOnUpdate()->cascadeOnDelete();

                $table->foreign('stock_id')
                    ->references('id')->on('stocks')
                    ->cascadeOnUpdate()->nullOnDelete();

                // Kalau tabel suppliers ada, aktifkan FK; kalau tidak ada, bisa dilepas.
                if (Schema::hasTable('suppliers')) {
                    $table->foreign('supplier_id')
                        ->references('id')->on('suppliers')
                        ->cascadeOnUpdate()->nullOnDelete();
                }
            });
        } else {
            // Tabel sudah ada → tambahkan kolom yang kurang saja
            Schema::table('order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('order_items', 'stock_id')) {
                    $table->unsignedBigInteger('stock_id')->nullable()->after('order_id');
                    $table->index('stock_id', 'order_items_stock_id_idx');
                    $table->foreign('stock_id')
                        ->references('id')->on('stocks')
                        ->cascadeOnUpdate()->nullOnDelete();
                }
                if (!Schema::hasColumn('order_items', 'notes')) {
                    $table->string('notes', 255)->nullable()->after('quantity');
                }
            });
        }
    }

    public function down(): void
    {
        // Rollback aman: hapus kolom yang kita tambahkan (jangan drop table agar data tidak hilang)
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (Schema::hasColumn('order_items', 'stock_id')) {
                    // Drop FK & index jika ada
                    try { $table->dropForeign(['stock_id']); } catch (\Throwable $e) {}
                    try { $table->dropIndex('order_items_stock_id_idx'); } catch (\Throwable $e) {}
                    try { $table->dropColumn('stock_id'); } catch (\Throwable $e) {}
                }
                if (Schema::hasColumn('order_items', 'notes')) {
                    try { $table->dropColumn('notes'); } catch (\Throwable $e) {}
                }
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'notes')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
