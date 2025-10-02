<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('production_issues')) {
            Schema::table('production_issues', function (Blueprint $table) {
                if (!Schema::hasColumn('production_issues','requested_at')) {
                    $table->dateTime('requested_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('production_issues','requested_by')) {
                    $table->unsignedBigInteger('requested_by')->nullable()->after('requested_at');
                    $table->index('requested_by', 'pi_requested_by_idx');
                    // Aktifkan FK kalau tabel users ada
                    if (Schema::hasTable('users')) {
                        $table->foreign('requested_by')
                              ->references('id')->on('users')
                              ->cascadeOnUpdate()
                              ->nullOnDelete();
                    }
                }
                // posted_at / posted_by biasanya sudah ada di project Anda.
                // Kalau belum, bisa buka komentar di bawah:
                /*
                if (!Schema::hasColumn('production_issues','posted_at')) {
                    $table->dateTime('posted_at')->nullable()->after('requested_by');
                }
                if (!Schema::hasColumn('production_issues','posted_by')) {
                    $table->unsignedBigInteger('posted_by')->nullable()->after('posted_at');
                    $table->index('posted_by', 'pi_posted_by_idx');
                    if (Schema::hasTable('users')) {
                        $table->foreign('posted_by')
                              ->references('id')->on('users')
                              ->cascadeOnUpdate()
                              ->nullOnDelete();
                    }
                }
                */
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_issues')) {
            Schema::table('production_issues', function (Blueprint $table) {
                if (Schema::hasColumn('production_issues','requested_by')) {
                    try { $table->dropForeign(['requested_by']); } catch (\Throwable $e) {}
                    try { $table->dropIndex('pi_requested_by_idx'); } catch (\Throwable $e) {}
                    try { $table->dropColumn('requested_by'); } catch (\Throwable $e) {}
                }
                if (Schema::hasColumn('production_issues','requested_at')) {
                    try { $table->dropColumn('requested_at'); } catch (\Throwable $e) {}
                }
                // rollback opsional untuk posted_* jika Anda aktifkan
            });
        }
    }
};
