<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Jika tabel stocks belum ada, lewati migration ini
        if (! Schema::hasTable('stocks')) {
            return;
        }

        Schema::table('stocks', function (Blueprint $t) {
            // Index untuk pencarian
            if (! $this->hasIndex('stocks', 'stocks_supplier_id_index')) {
                $t->index('supplier_id', 'stocks_supplier_id_index');
            }
            if (! $this->hasIndex('stocks', 'stocks_material_name_index')) {
                $t->index('material_name', 'stocks_material_name_index');
            }

            // Unique key kombinasi
            if (! $this->hasIndex('stocks', 'stocks_supplier_material_unit_unique')) {
                $t->unique(['supplier_id', 'material_name', 'unit'], 'stocks_supplier_material_unit_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stocks')) {
            return;
        }

        Schema::table('stocks', function (Blueprint $t) {
            if ($this->hasIndex('stocks', 'stocks_supplier_material_unit_unique')) {
                $t->dropUnique('stocks_supplier_material_unit_unique');
            }
            if ($this->hasIndex('stocks', 'stocks_supplier_id_index')) {
                $t->dropIndex('stocks_supplier_id_index');
            }
            if ($this->hasIndex('stocks', 'stocks_material_name_index')) {
                $t->dropIndex('stocks_material_name_index');
            }
        });
    }

    // Helper idempotent
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $schema = Schema::getConnection()->getDoctrineSchemaManager();
            $doctrineTable = $schema->introspectTable(
                Schema::getConnection()->getTablePrefix() . $table
            );
            return $doctrineTable->hasIndex($indexName);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
