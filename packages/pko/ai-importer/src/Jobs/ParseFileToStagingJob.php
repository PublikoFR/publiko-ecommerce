<?php

declare(strict_types=1);

namespace Pko\AiImporter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Actions\Types\LlmTransformAction;
use Pko\AiImporter\Enums\ErrorPolicy;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\LogPhase;
use Pko\AiImporter\Enums\RowFilter;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Notifications\ImportJobNotifier;
use Pko\AiImporter\Services\ActionPipeline;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\AiImporter\Services\ProgressCache;
use Pko\AiImporter\Services\SpreadsheetParser;
use Pko\AiImporter\Support\ConfigColumnExtractor;
use Pko\AiImporter\Support\ConfigDiagnostics;
use Pko\AiImporter\Support\MappingOrder;

/**
 * Parses the uploaded spreadsheet and populates `pko_ai_importer_staging`.
 *
 * Iteration is resumable: on failure, `last_processed_row` stores the row
 * number of the last row flushed to staging. Re-dispatching the job picks
 * up right after that row. The `row_limit` option caps the number of rows
 * for test runs.
 *
 * Secondary sheets referenced in `config.sheets` are indexed per-row by
 * `join_key` and exposed through `ExecutionContext::$sheets` so that
 * `multiline_aggregate` actions can resolve 1-N relationships.
 */
class ParseFileToStagingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 3;

    /**
     * File et connexion fixées ici pour TOUS les points de dispatch : la file
     * dédiée est consommée par le worker planifié séparé (routes/console.php),
     * la connexion « longue » a un `retry_after` supérieur au timeout du job.
     */
    public function __construct(public readonly int $importJobId)
    {
        $this->onConnection(config('ai-importer.queues.connection'));
        $this->onQueue(config('ai-importer.queues.parse', 'ai-importer-parse'));
    }

    public function handle(SpreadsheetParser $parser, ActionPipeline $pipeline): void
    {
        $job = ImportJob::query()->findOrFail($this->importJobId);

        if (! $job->config) {
            $this->fail($job, 'No config attached to job');

            return;
        }

        // Arrêtée par l'utilisateur avant que le worker ne la prenne : on ne la
        // relance pas dans son dos (le statut `parsing` posé ici l'aurait écrasé).
        if ($job->status === JobStatus::Cancelled) {
            $this->log($job, LogLevel::Warning, 'Préparation arrêtée avant son démarrage : rien n\'a été traité.');

            return;
        }

        $job->update([
            'status' => JobStatus::Parsing,
            'parse_started_at' => $job->parse_started_at ?? now(),
        ]);

        $this->log($job, LogLevel::Info, 'Début de la préparation', ['file' => $job->input_file_path]);

        // Une LlmConfig modifiée entre deux parses doit être relue : le cache
        // de configuration des actions IA vit le temps d'un job, pas du worker.
        LlmTransformAction::flushConfigCache();

        try {
            $disk = Storage::disk(config('ai-importer.storage.disk', 'local'));
            $absolutePath = $disk->path($job->input_file_path);

            $config = $job->config->config_data->getArrayCopy();
            // Stream XLSX/XLS au-delà du seuil (5 Mo par défaut) ; CSV toujours lu en entier.
            $fileSize = @filesize($absolutePath) ?: 0;
            $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            $streamThreshold = (int) config('ai-importer.defaults.stream_threshold_bytes', 5 * 1024 * 1024);
            if (in_array($ext, ['xlsx', 'xls'], true) && $fileSize > $streamThreshold) {
                $parser->loadStreamed($absolutePath, $config, (int) ($job->chunk_size ?: 2000));
            } else {
                $parser->load($absolutePath, $config);
            }

            $primary = $parser->primarySheetName();
            // Fichier et config ne correspondent pas : arrêt net, SANS nouvelle
            // tentative de la file (relancer ne changerait rien au fichier).
            $mismatch = $this->fileMismatch($job, $parser, $config, $primary);
            if ($mismatch !== null) {
                $this->fail($job, $mismatch);

                return;
            }
            $total = $parser->countRows($primary);
            $job->update(['total_rows' => $total]);

            $joinKey = $parser->joinKeyName();
            $mapping = MappingOrder::declared($config);

            // Option `columns_to_process` : restreint le mapping à un sous-ensemble
            // de clés (les autres ne sont pas calculées au parse). Vide = tout.
            $columnsToProcess = array_values(array_map(
                'strval',
                (array) ($job->options['columns_to_process'] ?? []),
            ));
            if ($columnsToProcess !== []) {
                // Les colonnes STATIQUES (valeur par défaut seule : fournisseur,
                // classe de taxe, frais de port…) ne sont pas proposées dans la
                // grille « colonnes à calculer » : elles ne coûtent rien et font
                // partie de la config, on les garde toujours. Sans cela elles
                // disparaissaient du staging (produits importés sans fournisseur).
                $selected = array_flip($columnsToProcess);
                $mapping = array_filter(
                    $mapping,
                    static fn (mixed $columnConfig, int|string $key): bool => isset($selected[(string) $key])
                        || ConfigColumnExtractor::isStatic($columnConfig),
                    ARRAY_FILTER_USE_BOTH,
                );
            }
            // Colonnes IA calculées après les autres : leurs `input_columns`
            // désignent des clés de sortie déjà transformées.
            $mapping = MappingOrder::sorted($mapping);
            // Filtre « nouveaux produits / produits existants » appliqué dès la
            // préparation : une ligne qui ne sera pas importée n'est pas calculée
            // au-delà de ses colonnes simples — aucun appel IA payé pour rien.
            $filterWriter = app(LunarProductWriter::class)->configure($job->options);
            $rowFilter = $filterWriter->rowFilter();
            $skippedByFilter = 0;

            $chunkEvery = (int) config('ai-importer.defaults.checkpoint_every', 100);
            // Avec des actions IA (plusieurs secondes par ligne), attendre
            // `checkpoint_every` lignes laisserait la console vide pendant des
            // minutes : on écrit aussi dès que ce délai est écoulé.
            $flushSeconds = (float) config('ai-importer.defaults.log_flush_seconds', 2);
            $lastFlush = microtime(true);
            $rowLimit = $job->row_limit;
            $policy = $job->error_policy instanceof ErrorPolicy
                ? $job->error_policy
                : ErrorPolicy::from((string) $job->error_policy);

            // Sémantique de reprise unique : `last_processed_row` = nombre de
            // lignes DATA déjà traitées (succès OU erreur). On le passe tel
            // quel à iterateRows() qui l'interprète comme un offset de
            // comptage (cf. SpreadsheetParser::iterateRows). « Relancer le
            // parse » reprend ainsi exactement après la dernière ligne traitée,
            // sans saut ni doublon.
            $startAfter = (int) ($job->last_processed_row ?? 0);
            $processed = $startAfter;
            $stagingCount = (int) $job->staging_count;
            $errors = 0;
            $stopped = false;
            $limited = false;
            $cancelled = false;

            /** @var array<int, array<string, mixed>> Batch de logs INFO à insérer en bloc. */
            $logBatch = [];

            foreach ($parser->iterateRows($primary, $startAfter) as $rowNumber => $row) {
                if ($rowLimit !== null && $processed >= $rowLimit) {
                    $limited = true;
                    break;
                }

                // Isolation par ligne : une ligne en erreur (action mal formée,
                // échec LLM, cellule invalide…) ne doit JAMAIS avorter tout le
                // job. Elle est journalisée, persistée en staging au statut
                // Error, puis le `error_policy` décide de continuer ou d'arrêter.
                $mapped = [];
                $halt = false;

                try {
                    $sheets = $joinKey !== null
                        ? $parser->secondarySheetsFor($row[$joinKey] ?? '')
                        : [];

                    $ctx = new ExecutionContext(
                        job: $job,
                        row: $row,
                        sheets: $sheets,
                        rowNumber: $rowNumber,
                    );

                    $filterChecked = $rowFilter === RowFilter::All;
                    $excluded = false;

                    foreach ($mapping as $outputKey => $columnConfig) {
                        // Les colonnes IA passent en dernier (MappingOrder) : on
                        // tranche juste avant la première, sur les colonnes déjà calculées.
                        if (! $filterChecked && ConfigColumnExtractor::usesAi($columnConfig)) {
                            $filterChecked = true;
                            $excluded = $this->excludedByFilter($filterWriter, $rowFilter, $mapped);
                            if ($excluded) {
                                break;
                            }
                        }

                        $srcCol = $columnConfig['col'] ?? null;
                        $srcSheet = $columnConfig['sheet'] ?? null;

                        // Résolution de la valeur initiale selon la feuille source :
                        //  - feuille primaire (ou absente)  → colonne de la row courante.
                        //  - feuille secondaire relation=one → colonne de l'unique row jointe.
                        //  - feuille secondaire relation=many → laissée à `multiline_aggregate`.
                        if ($srcCol === null) {
                            $initial = null;
                        } elseif ($srcSheet === null || $srcSheet === '' || $srcSheet === $primary) {
                            $initial = $row[$srcCol] ?? null;
                        } else {
                            $relation = (string) ($config['sheets'][$srcSheet]['relation'] ?? 'many');
                            $initial = $relation === 'one'
                                ? (($sheets[$srcSheet][0] ?? [])[$srcCol] ?? null)
                                : null;
                        }

                        $value = $pipeline->run($initial, (array) $columnConfig, $ctx);
                        $mapped[$outputKey] = $value;
                        $ctx->setOutput((string) $outputKey, $value);
                    }

                    if (! $filterChecked) {
                        $excluded = $this->excludedByFilter($filterWriter, $rowFilter, $mapped);
                    }
                    if ($excluded) {
                        $skippedByFilter++;
                    }

                    // Anti-doublon à la reprise : updateOrCreate sur la clé
                    // unique (import_job_id, row_number).
                    $record = StagingRecord::updateOrCreate(
                        ['import_job_id' => $job->id, 'row_number' => $rowNumber],
                        [
                            'data' => $mapped,
                            'status' => $excluded ? StagingStatus::Skipped : StagingStatus::Pending,
                            'error_message' => $excluded ? 'Écartée par le filtre « '.$rowFilter->label().' »' : null,
                        ],
                    );
                    if ($record->wasRecentlyCreated) {
                        $stagingCount++;
                    }

                    $ref = (string) ($mapped['reference'] ?? '');
                    $name = (string) ($mapped['name'] ?? '');
                    $totalLabel = $total !== null ? (string) $total : '?';
                    $logBatch[] = ImportLog::row(
                        $job->id,
                        LogPhase::Parse,
                        LogLevel::Info,
                        'Ligne '.($processed + 1)."/{$totalLabel} : [{$ref}] {$name}".($excluded ? ' — écartée par le filtre' : ''),
                        $rowNumber,
                    );
                } catch (\Throwable $e) {
                    $errors++;
                    $record = StagingRecord::updateOrCreate(
                        ['import_job_id' => $job->id, 'row_number' => $rowNumber],
                        ['data' => $mapped, 'status' => StagingStatus::Error, 'error_message' => $e->getMessage()],
                    );
                    if ($record->wasRecentlyCreated) {
                        $stagingCount++;
                    }
                    // Lignes précédentes d'abord : la console reste dans l'ordre.
                    $this->flushLogBatch($logBatch);
                    $this->log($job, LogLevel::Error, "Ligne #{$rowNumber}: {$e->getMessage()}", [], $rowNumber);

                    // ignore → on continue ; stop/rollback → arrêt propre du
                    // parse (rien à rollback à ce stade : aucune écriture Lunar).
                    $halt = $policy !== ErrorPolicy::Ignore;
                }

                $processed++;

                if ($halt) {
                    $stopped = true;
                    $this->flushLogBatch($logBatch);
                    $this->persistProgress($job, $processed, $stagingCount, $total);
                    break;
                }

                if ($processed % $chunkEvery === 0 || microtime(true) - $lastFlush >= $flushSeconds) {
                    $this->flushLogBatch($logBatch);
                    $this->persistProgress($job, $processed, $stagingCount, $total);
                    $lastFlush = microtime(true);

                    // Bouton « Annuler » : le statut est relu à chaque écriture.
                    if (ImportJob::query()->whereKey($job->id)->toBase()->value('status') === JobStatus::Cancelled->value) {
                        $cancelled = true;
                        break;
                    }
                }
            }

            $this->flushLogBatch($logBatch);

            if ($cancelled) {
                $this->log($job, LogLevel::Warning, "Préparation annulée par l'utilisateur après {$processed} ligne(s).", [
                    'last_processed_row' => $processed,
                ]);

                return;
            }

            if ($stopped) {
                $job->update([
                    'status' => JobStatus::Error,
                    'error_message' => "Parse arrêté selon la politique « {$policy->label()} » après {$errors} erreur(s).",
                ]);
                $this->log($job, LogLevel::Error, "Parse interrompu (politique {$policy->value}) : {$errors} erreur(s).", [
                    'last_processed_row' => $processed,
                ]);

                return;
            }

            // Fichier lu jusqu'au bout : le nombre réel de lignes est connu. En
            // mode streamé, `countRows()` (listWorksheetInfo) peut compter des
            // lignes vides mais formatées en fin de feuille — on recale.
            if (! $limited) {
                $total = $processed;
            }

            $job->update([
                'status' => JobStatus::Parsed,
                'total_rows' => $total,
                'processed_rows' => $processed,
                'staging_count' => $stagingCount,
                'last_processed_row' => $processed,
                'parse_completed_at' => now(),
            ]);
            ProgressCache::set($job, $processed, $total);

            $this->log($job, LogLevel::Success, 'Préparation terminée', [
                'total_rows' => $total,
                'staging_rows' => $stagingCount,
                'error_rows' => $errors,
                'skipped_by_filter' => $skippedByFilter,
            ]);

            ImportJobNotifier::parseCompleted($job->fresh());
        } catch (\Throwable $e) {
            $this->fail($job, $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        ImportJob::query()->where('id', $this->importJobId)->update([
            'status' => JobStatus::Error,
            'error_message' => $e->getMessage(),
        ]);
    }

    /**
     * La ligne est-elle écartée par le filtre de présence ? Une ligne qu'on ne
     * sait pas identifier (référence vide, rapprochement ambigu) n'est jamais
     * écartée ici : l'import tranchera, avec son message d'erreur.
     *
     * @param  array<string, mixed>  $mapped
     */
    private function excludedByFilter(LunarProductWriter $writer, RowFilter $filter, array $mapped): bool
    {
        if ($filter === RowFilter::All) {
            return false;
        }

        $exists = $writer->productExists($mapped);

        return $exists !== null && ! $filter->allows($exists);
    }

    /**
     * Contrôle du fichier contre la config, avant la première ligne : une
     * feuille absente arrête la préparation avec un message clair (au lieu d'une
     * erreur identique sur chaque ligne) ; une colonne désignée par un intitulé
     * absent de la ligne d'en-têtes est signalée dans la console (le champ
     * restera vide), sans bloquer.
     *
     * @param  array<string, mixed>  $config
     * @return string|null message d'arrêt, null si la préparation peut démarrer
     */
    private function fileMismatch(ImportJob $job, SpreadsheetParser $parser, array $config, string $primary): ?string
    {
        $available = $parser->sheetNames();
        $declared = array_map('strval', array_keys(array_filter((array) ($config['sheets'] ?? []), 'is_array')));

        $missing = array_values(array_diff(array_unique([$primary, ...$declared]), $available));
        if ($missing !== []) {
            return sprintf(
                'Feuille(s) absente(s) du fichier : %s. Feuilles trouvées : %s. Vérifiez le fichier ou la configuration.',
                implode(', ', $missing),
                implode(', ', $available) ?: 'aucune',
            );
        }

        foreach (ConfigDiagnostics::namedColumnsBySheet($config, $primary) as $sheetName => $columns) {
            if (! $parser->hasSheet($sheetName)) {
                continue;
            }
            $absent = array_values(array_diff($columns, $parser->headerNames($sheetName)));
            if ($absent !== []) {
                $this->log($job, LogLevel::Warning, sprintf(
                    'Feuille « %s » : colonne(s) introuvable(s) dans la ligne d\'en-têtes — %s. Les champs qui en dépendent resteront vides.',
                    $sheetName,
                    implode(', ', $absent),
                ), ['sheet' => $sheetName, 'columns' => $absent]);
            }
        }

        return null;
    }

    private function persistProgress(ImportJob $job, int $processed, int $stagingCount, ?int $total): void
    {
        $job->update([
            'processed_rows' => $processed,
            'staging_count' => $stagingCount,
            'last_processed_row' => $processed,
        ]);
        ProgressCache::set($job, $processed, $total);
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     */
    private function flushLogBatch(array &$batch): void
    {
        if ($batch === []) {
            return;
        }

        DB::table('pko_ai_importer_logs')->insert($batch);
        $batch = [];
    }

    private function fail(ImportJob $job, string $message): void
    {
        DB::transaction(function () use ($job, $message): void {
            $job->update([
                'status' => JobStatus::Error,
                'error_message' => $message,
            ]);
            $this->log($job, LogLevel::Error, $message);
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(ImportJob $job, LogLevel $level, string $message, array $context = [], ?int $rowNumber = null): void
    {
        ImportLog::create([
            'import_job_id' => $job->id,
            'phase' => LogPhase::Parse,
            'row_number' => $rowNumber,
            'level' => $level,
            'message' => $message,
            'context' => $context !== [] ? $context : null,
            'created_at' => now(),
        ]);
    }
}
