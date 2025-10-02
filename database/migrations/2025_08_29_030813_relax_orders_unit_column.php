<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'unit')) {

            DB::statement("ALTER TABLE orders MODIFY unit VARCHAR(255) NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'unit')) {

            DB::statement("ALTER TABLE orders MODIFY unit VARCHAR(255) NOT NULL");
        }
    }
};
