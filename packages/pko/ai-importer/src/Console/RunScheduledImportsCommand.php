<?php

declare(strict_types=1);

namespace Pko\AiImporter\Console;

use Illuminate\Console\Command;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Jobs\ImportStagingToLunarJob;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImportJob;

/**
 * Tick du cron de l'import : c'est LUI qui déclenche les deux phases longues
 * du pipeline, jamais l'action UI directement. Deux passes indépendantes :
 *
 *  1. PARSE — les jobs `status=pending` (« prêt à préparer ») avec une config
 *     attachée : dispatch de `ParseFileToStagingJob` (pending → parsing → parsed).
 *     Déclenché dès le tick suivant la création (aucun `scheduled_at` requis :
 *     la préparation démarre au plus tôt).
 *  2. IMPORT — les jobs `status=parsed` marqués « prêt à importer »
 *     (`import_status ∈ {pending, scheduled}` + `scheduled_at <= now`) :
 *     dispatch de `ImportStagingToLunarJob` (→ importing → imported).
 *
 * Chaque job est sorti de son état éligible AVANT le dispatch (parsing / queued)
 * pour qu'un tick ultérieur ne le re-sélectionne pas si le worker prend du retard
 * — évite double parse / double import.
 *
 * Planifié toutes les quelques minutes (voir `routes/console.php`). Manuellement
 * déclenchable via l'action « Exécuter CRON » ou `make artisan
 * CMD='ai-importer:run-scheduled'`. `--dry` liste sans rien dispatcher (utilisé
 * par le bouton « Tester CRON »).
 */
class RunScheduledImportsCommand extends Command
{
    protected $signature = 'ai-importer:run-scheduled {--dry : List what would run without dispatching}';

    protected $description = 'Déclenche les préparations et imports Lunar dus (2 phases pilotées par le cron).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $parsed = $this->dispatchDueParses($dry);
        $imported = $this->dispatchDueImports($dry);

        if ($parsed === 0 && $imported === 0) {
            $this->info('Aucun job dû (préparation ou import).');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s%d préparation(s) + %d import(s) %s.',
            $dry ? 'DRY — ' : '',
            $parsed,
            $imported,
            $dry ? 'éligibles' : 'dispatché(s)',
        ));

        return self::SUCCESS;
    }

    /**
     * Phase 1 — préparations en attente du cron (status=pending, config présente).
     */
    private function dispatchDueParses(bool $dry): int
    {
        $due = ImportJob::query()
            ->where('status', JobStatus::Pending->value)
            ->whereNotNull('config_id') // les CSV pré-préparés naissent déjà « parsed »
            ->orderBy('id')
            ->get();

        foreach ($due as $job) {
            $this->line(sprintf(
                '%s [parse] #%d — %s',
                $dry ? '[dry]' : '→',
                $job->id,
                $job->uuid,
            ));

            if ($dry) {
                continue;
            }

            // Sortie de l'état éligible avant dispatch : la requête ne cible que
            // `pending`, donc un tick ultérieur ne re-parsera pas ce job.
            $job->update(['status' => JobStatus::Parsing]);
            ParseFileToStagingJob::dispatch($job->id)
                ->onQueue(config('ai-importer.queues.parse', 'ai-importer-parse'));
        }

        return $due->count();
    }

    /**
     * Phase 2 — imports Lunar programmés dus (status=parsed, scheduled_at échu).
     */
    private function dispatchDueImports(bool $dry): int
    {
        $due = ImportJob::query()
            ->where('status', JobStatus::Parsed->value)
            ->whereIn('import_status', [ImportStatus::Pending->value, ImportStatus::Scheduled->value])
            ->whereNotNull('scheduled_at') // le cron ne prend que les imports EXPLICITEMENT programmés
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();

        foreach ($due as $job) {
            $this->line(sprintf(
                '%s [import] #%d — %s (programmé %s)',
                $dry ? '[dry]' : '→',
                $job->id,
                $job->uuid,
                $job->scheduled_at?->diffForHumans() ?? 'maintenant',
            ));

            if ($dry) {
                continue;
            }

            // Passage à Queued AVANT le dispatch : cet état est hors de la requête
            // d'éligibilité ([Pending, Scheduled]), donc un tick cron ultérieur ne
            // re-matchera plus ce job même si le worker queue prend du retard. Évite
            // le double dispatch (et donc le double import des produits).
            $job->update(['import_status' => ImportStatus::Queued]);
            ImportStagingToLunarJob::dispatch($job->id)
                ->onQueue(config('ai-importer.queues.import', 'ai-importer-import'));
        }

        return $due->count();
    }
}
