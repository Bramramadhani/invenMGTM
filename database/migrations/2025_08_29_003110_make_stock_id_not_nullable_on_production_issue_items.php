<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::table('production_issue_items')->whereNull('stock_id')->exists()) {
            throw new \RuntimeException(
                'Masih ada production_issue_items.stock_id = NULL. Backfill dulu sebelum menjadikannya NOT NULL.'
            );
        }

        DB::statement('ALTER TABLE production_issue_items MODIFY stock_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE production_issue_items MODIFY stock_id BIGINT UNSIGNED NULL');
    }
};
