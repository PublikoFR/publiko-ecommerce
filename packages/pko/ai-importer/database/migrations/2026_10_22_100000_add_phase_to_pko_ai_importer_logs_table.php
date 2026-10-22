<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sépare les logs de préparation (parse) des logs d'import (écriture Lunar)
 * pour les afficher dans deux consoles distinctes. Les logs antérieurs restent
 * à NULL et sont rangés côté préparation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pko_ai_importer_logs', function (Blueprint $table): void {
            $table->string('phase', 16)->nullable()->after('import_job_id');
            $table->index(['import_job_id', 'phase'], 'pko_logs_job_phase_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pko_ai_importer_logs', function (Blueprint $table): void {
            $table->dropIndex('pko_logs_job_phase_idx');
            $table->dropColumn('phase');
        });
    }
};
