<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $t) {
            // nomor referensi eksternal (DO / SJ dari supplier)
            $t->string('supplier_do_number')->nullable()->after('receipt_date');

            // status dokumen
            // catatan: jika pakai SQLite untuk testing/CI, enum bisa diganti string.
            $t->enum('status', ['draft','posted','void'])
              ->default('draft')
              ->after('receipt_number');

            // anti double-submit
            $t->string('idempotency_token')->nullable()->unique()->after('status');

            // jejak user & waktu siklus dokumen
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete()->after('notes');
            $t->timestamp('posted_at')->nullable()->after('received_by');
            $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete()->after('posted_at');
            $t->timestamp('voided_at')->nullable()->after('posted_by');
            $t->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete()->after('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $t) {
            // buang kolom-kolom tambahan
            $t->dropConstrainedForeignId('received_by'); // drop fk + kolom
            $t->dropConstrainedForeignId('posted_by');
            $t->dropConstrainedForeignId('voided_by');

            $t->dropColumn([
                'supplier_do_number',
                'status',
                'idempotency_token',
                'posted_at',
                'voided_at',
            ]);
        });
    }
};
