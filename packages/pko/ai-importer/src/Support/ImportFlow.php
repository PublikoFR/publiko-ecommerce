<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use Carbon\CarbonInterface;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Jobs\ImportStagingToLunarJob;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImportJob;

/**
 * Transitions d'un import déclenchées depuis l'interface : un seul endroit pour
 * lancer, programmer, reprendre ou annuler une phase.
 *
 * Règle de déclenchement — les deux phases longues ne tournent JAMAIS dans la
 * requête web :
 *  - file d'attente réelle (database / redis) → le job part tout de suite sur sa
 *    file dédiée, le worker planifié le prend dans la minute ;
 *  - connexion `sync` → rien n'est dispatché (le job s'exécuterait dans la
 *    requête) : l'import reste éligible et le planificateur
 *    (`ai-importer:run-scheduled`, toutes les 2 min) le déclenche.
 *
 * Dans les deux cas le job est sorti de son état éligible AVANT le dispatch
 * (`parsing` / `queued`) : un passage du planificateur ne le relance pas.
 */
final class ImportFlow
{
    /** La file des imports est-elle traitée par un worker (pas `sync`) ? */
    public static function runsInBackground(): bool
    {
        $connection = config('ai-importer.queues.connection') ?: config('queue.default');

        return config("queue.connections.{$connection}.driver", 'sync') !== 'sync';
    }

    /**
     * Lance (ou relance) la préparation.
     *
     * @return bool true = parti tout de suite, false = pris au prochain passage du planificateur
     */
    public static function startParse(ImportJob $job): bool
    {
        if (! self::runsInBackground()) {
            $job->update(['status' => JobStatus::Pending, 'error_message' => null, 'stopped_by_user' => false]);

            return false;
        }

        $job->update(['status' => JobStatus::Parsing, 'error_message' => null, 'stopped_by_user' => false]);
        ParseFileToStagingJob::dispatch($job->id);

        return true;
    }

    /**
     * Lance l'écriture dans le catalogue, maintenant ou à une date.
     *
     * Après un import annulé (restauré), la sauvegarde précédente est oubliée :
     * une nouvelle est prise au démarrage, et l'annulation redevient possible.
     *
     * @return string `now` (parti), `soon` (prochain passage du planificateur) ou `scheduled` (date future)
     */
    public static function startImport(ImportJob $job, ?CarbonInterface $at = null): string
    {
        $reset = ['error_message' => null, 'stopped_by_user' => false];
        if ($job->import_status === ImportStatus::RolledBack) {
            $reset += ['backup_path' => null, 'rollback_completed' => false, 'imported_count' => 0, 'error_count' => 0];
        }

        if ($at !== null && $at->isFuture()) {
            $job->update($reset + ['import_status' => ImportStatus::Scheduled, 'scheduled_at' => $at]);

            return 'scheduled';
        }

        if (! self::runsInBackground()) {
            $job->update($reset + ['import_status' => ImportStatus::Scheduled, 'scheduled_at' => now()]);

            return 'soon';
        }

        $job->update($reset + ['import_status' => ImportStatus::Queued, 'scheduled_at' => now()]);
        ImportStagingToLunarJob::dispatch($job->id);

        return 'now';
    }

    public static function cancelSchedule(ImportJob $job): void
    {
        $job->update(['import_status' => ImportStatus::Pending, 'scheduled_at' => null]);
    }

    public static function cancelParse(ImportJob $job): void
    {
        $job->update(['status' => JobStatus::Cancelled, 'stopped_by_user' => true]);
    }

    /**
     * Remet les lignes en erreur « à importer » (après correction), pour les
     * rejouer sans toucher aux lignes déjà écrites.
     *
     * @return int nombre de lignes remises en attente
     */
    public static function retryErrorRows(ImportJob $job): int
    {
        return $job->stagingRecords()
            ->where('status', StagingStatus::Error->value)
            ->update(['status' => StagingStatus::Pending->value, 'error_message' => null]);
    }
}
