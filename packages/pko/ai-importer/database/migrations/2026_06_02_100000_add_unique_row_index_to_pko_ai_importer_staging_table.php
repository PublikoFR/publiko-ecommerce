<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anti-doublon staging : garantit qu'une (import_job_id, row_number) est
 * unique. Permet à `ParseFileToStagingJob` d'utiliser `updateOrCreate` à la
 * reprise sans créer de lignes en double si le checkpoint était en retard
 * sur les écritures réelles.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Purge d'éventuels doublons hérités du double-comptage de reprise
        // (on conserve la ligne au plus petit id) avant de poser l'index unique.
        // Formulation portable MySQL/SQLite (pas de DELETE multi-tables).
        $dupes = DB::table('pko_ai_importer_staging')
            ->select('import_job_id', 'row_number', DB::raw('MIN(id) as keep_id'))
            ->groupBy('import_job_id', 'row_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dupe) {
            DB::table('pko_ai_importer_staging')
                ->where('import_job_id', $dupe->import_job_id)
                ->where('row_number', $dupe->row_number)
                ->where('id', '!=', $dupe->keep_id)
                ->delete();
        }

        Schema::table('pko_ai_importer_staging', function (Blueprint $table): void {
            // Remplace l'index composite non-unique par sa variante unique.
            $table->dropIndex('pko_staging_job_row_idx');
            $table->unique(['import_job_id', 'row_number'], 'pko_staging_job_row_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pko_ai_importer_staging', function (Blueprint $table): void {
            $table->dropUnique('pko_staging_job_row_unique');
            $table->index(['import_job_id', 'row_number'], 'pko_staging_job_row_idx');
        });
    }
};
