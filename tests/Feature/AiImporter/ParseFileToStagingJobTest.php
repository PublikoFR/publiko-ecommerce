<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ActionRegistry;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\LogPhase;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\ActionPipeline;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\AiImporter\Services\SpreadsheetParser;
use Tests\TestCase;

/**
 * Action de test qui lève une exception dès qu'une cellule contient « BOOM ».
 * Permet de simuler une ligne en erreur de façon déterministe.
 */
final class BoomAction extends Action
{
    public static function type(): string
    {
        return 'test_boom';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        if (is_string($value) && str_contains($value, 'BOOM')) {
            throw new \RuntimeException('valeur explosive');
        }

        return $value;
    }
}

class ParseFileToStagingJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // bootDefaults() AVANT register() : sinon la map devient non-vide et
        // les actions par défaut (change_case, etc.) ne sont jamais seedées.
        ActionRegistry::bootDefaults();
        ActionRegistry::register('test_boom', BoomAction::class);
    }

    public function test_parses_a_csv_file_through_the_pipeline_into_staging(): void
    {
        Storage::fake('local');

        $csv = "reference,name,price\nSKU-1,Moteur Somfy,19900\nSKU-2,Rail alu,4500\n";
        Storage::disk('local')->put('ai-importer/inputs/test.csv', $csv);

        $config = ImporterConfig::create([
            'name' => 'test-csv',
            'config_data' => [
                'primary_sheet' => 'Worksheet',
                'sheets' => ['Worksheet' => ['skip_first_row' => true]],
                'mapping' => [
                    'reference' => ['col' => 'reference'],
                    'name' => [
                        'col' => 'name',
                        'actions' => [['type' => 'change_case', 'mode' => 'upper']],
                    ],
                    'price_cents' => [
                        'col' => 'price',
                        'actions' => [['type' => 'math', 'operation' => 'multiply', 'value' => 1]],
                    ],
                ],
            ],
        ]);

        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'ai-importer/inputs/test.csv',
            'status' => JobStatus::Pending,
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        (new ParseFileToStagingJob($job->id))->handle(
            app(SpreadsheetParser::class),
            app(ActionPipeline::class),
        );

        $job->refresh();

        $this->assertSame(JobStatus::Parsed, $job->status);
        $this->assertSame(2, $job->staging_count);
        $this->assertSame(2, $job->stagingRecords()->count());

        $first = $job->stagingRecords()->orderBy('row_number')->first();
        $data = (array) $first->data;
        $this->assertSame('SKU-1', $data['reference']);
        $this->assertSame('MOTEUR SOMFY', $data['name']);
        $this->assertSame(StagingStatus::Pending, $first->status);
    }

    public function test_ignore_policy_marks_failing_row_error_and_continues(): void
    {
        $job = $this->makeJobWithBoomConfig('ignore');

        $this->runParse($job);
        $job->refresh();

        // Le job va au bout malgré la ligne fautive.
        $this->assertSame(JobStatus::Parsed, $job->status);
        $this->assertSame(3, $job->stagingRecords()->count());

        $byStatus = $job->stagingRecords()->get()->keyBy(fn ($r): int => $r->row_number);
        $this->assertSame(StagingStatus::Pending, $byStatus[2]->status); // SKU-1
        $this->assertSame(StagingStatus::Error, $byStatus[3]->status);   // SKU-2 (BOOM)
        $this->assertNotNull($byStatus[3]->error_message);
        $this->assertSame(StagingStatus::Pending, $byStatus[4]->status); // SKU-3

        // Reprise cohérente : compteur de lignes data traitées.
        $this->assertSame(3, $job->last_processed_row);
    }

    public function test_stop_policy_halts_parse_on_first_error(): void
    {
        $job = $this->makeJobWithBoomConfig('stop');

        $this->runParse($job);
        $job->refresh();

        // Arrêt propre : job en erreur, lignes après la fautive non parsées.
        $this->assertSame(JobStatus::Error, $job->status);
        $this->assertNotNull($job->error_message);

        $rows = $job->stagingRecords()->pluck('status', 'row_number');
        $this->assertCount(2, $rows); // SKU-1 + SKU-2, pas SKU-3
        $this->assertSame(StagingStatus::Pending->value, $rows[2]->value);
        $this->assertSame(StagingStatus::Error->value, $rows[3]->value);
        $this->assertArrayNotHasKey(4, $rows->toArray());

        // last_processed_row = nb de lignes traitées (succès + erreur) = 2.
        $this->assertSame(2, $job->last_processed_row);
    }

    public function test_resume_continues_after_last_processed_row_without_duplicates(): void
    {
        Storage::fake('local');

        $csv = "reference,name\nSKU-1,Alpha\nSKU-2,Beta\nSKU-3,Gamma\n";
        Storage::disk('local')->put('ai-importer/inputs/resume.csv', $csv);

        $config = ImporterConfig::create([
            'name' => 'resume-csv',
            'config_data' => [
                'primary_sheet' => 'Worksheet',
                'sheets' => ['Worksheet' => ['skip_first_row' => true]],
                'mapping' => [
                    'reference' => ['col' => 'reference'],
                    'name' => ['col' => 'name'],
                ],
            ],
        ]);

        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'ai-importer/inputs/resume.csv',
            'status' => JobStatus::Pending,
            'import_status' => 'pending',
            'error_policy' => 'ignore',
            'row_limit' => 2,
        ]);

        // Premier passage : plafonné à 2 lignes.
        $this->runParse($job);
        $job->refresh();

        $this->assertSame(2, $job->stagingRecords()->count());
        $this->assertSame(2, $job->staging_count);
        $this->assertSame(2, $job->last_processed_row);
        $this->assertEqualsCanonicalizing(
            [2, 3],
            $job->stagingRecords()->pluck('row_number')->all(),
        );

        // Reprise : on lève le plafond et on relance.
        $job->update(['row_limit' => null, 'status' => JobStatus::Error]);
        $job->refresh();

        $this->runParse($job);
        $job->refresh();

        // Reprend exactement après la 2e ligne : 3 records distincts, aucun doublon.
        $this->assertSame(JobStatus::Parsed, $job->status);
        $this->assertSame(3, $job->stagingRecords()->count());
        $this->assertSame(3, $job->staging_count);
        $this->assertSame(3, $job->last_processed_row);
        $this->assertEqualsCanonicalizing(
            [2, 3, 4],
            $job->stagingRecords()->pluck('row_number')->all(),
        );
    }

    public function test_row_logs_are_flushed_live_and_tagged_as_parse_phase(): void
    {
        // Délai nul : chaque ligne est écrite aussitôt, comme sur un parse IA lent.
        config(['ai-importer.defaults.log_flush_seconds' => 0]);
        $job = $this->makeJobWithBoomConfig('ignore');

        $seenBeforeNextRow = [];
        StagingRecord::saved(function (StagingRecord $record) use (&$seenBeforeNextRow): void {
            $seenBeforeNextRow[$record->row_number] = ImportLog::query()
                ->where('import_job_id', $record->import_job_id)
                ->whereNotNull('row_number')
                ->count();
        });

        $this->runParse($job);

        // Au moment d'écrire la ligne 4, les logs des lignes 2 et 3 sont déjà en base.
        $this->assertSame(2, $seenBeforeNextRow[4]);

        $logs = ImportLog::query()->where('import_job_id', $job->id)->get();
        $this->assertTrue($logs->every(fn (ImportLog $log): bool => $log->phase === LogPhase::Parse));
        $this->assertTrue($logs->contains(fn (ImportLog $log): bool => str_starts_with($log->message, 'Ligne 1/')));
        // Heure posée par l'application, pas par le défaut SQL (fuseau de la base).
        $this->assertTrue($logs->every(fn (ImportLog $log): bool => abs($log->created_at->diffInSeconds(now())) < 60));
    }

    public function test_cancel_stops_the_parse_and_logs_it(): void
    {
        config(['ai-importer.defaults.log_flush_seconds' => 0]);
        $job = $this->makeJobWithBoomConfig('ignore');

        // L'utilisateur clique « Annuler » pendant la première ligne.
        StagingRecord::saved(function (StagingRecord $record): void {
            ImportJob::query()->whereKey($record->import_job_id)->update(['status' => JobStatus::Cancelled]);
        });

        $this->runParse($job);
        $job->refresh();

        $this->assertSame(JobStatus::Cancelled, $job->status);
        $this->assertSame(1, $job->stagingRecords()->count());
        $this->assertSame(1, $job->last_processed_row);
        $this->assertTrue(ImportLog::query()
            ->where('import_job_id', $job->id)
            ->where('message', 'like', 'Préparation annulée%')
            ->exists());
    }

    private function makeJobWithBoomConfig(string $errorPolicy): ImportJob
    {
        Storage::fake('local');

        $csv = "reference,name,flag\nSKU-1,Alpha,ok\nSKU-2,Beta,BOOM\nSKU-3,Gamma,ok\n";
        Storage::disk('local')->put('ai-importer/inputs/boom.csv', $csv);

        $config = ImporterConfig::create([
            'name' => 'boom-csv',
            'config_data' => [
                'primary_sheet' => 'Worksheet',
                'sheets' => ['Worksheet' => ['skip_first_row' => true]],
                'mapping' => [
                    'reference' => ['col' => 'reference'],
                    'name' => ['col' => 'name'],
                    'flag' => [
                        'col' => 'flag',
                        'actions' => [['type' => 'test_boom']],
                    ],
                ],
            ],
        ]);

        return ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'ai-importer/inputs/boom.csv',
            'status' => JobStatus::Pending,
            'import_status' => 'pending',
            'error_policy' => $errorPolicy,
        ]);
    }

    private function runParse(ImportJob $job): void
    {
        (new ParseFileToStagingJob($job->id))->handle(
            app(SpreadsheetParser::class),
            app(ActionPipeline::class),
        );
    }

    public function test_streamed_parse_recounts_total_rows_without_trailing_formatted_rows(): void
    {
        Storage::fake('local');
        config(['ai-importer.defaults.stream_threshold_bytes' => 0]); // force le mode streamé

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Produits');
        $sheet->fromArray([['reference', 'name'], ['SKU-1', 'Un'], ['SKU-2', 'Deux'], ['SKU-3', 'Trois']]);
        // Lignes vides mais formatées en fin de feuille : listWorksheetInfo les compte.
        $sheet->getStyle('A20:B20')->getFont()->setBold(true);
        $tmp = tempnam(sys_get_temp_dir(), 'stream').'.xlsx';
        (new Xlsx($book))->save($tmp);
        Storage::disk('local')->put('ai-importer/inputs/stream.xlsx', (string) file_get_contents($tmp));
        @unlink($tmp);

        $config = ImporterConfig::create([
            'name' => 'test-stream',
            'config_data' => [
                'primary_sheet' => 'Produits',
                'sheets' => ['Produits' => ['skip_first_row' => true]],
                'mapping' => ['reference' => ['col' => 'reference'], 'name' => ['col' => 'name']],
            ],
        ]);
        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'ai-importer/inputs/stream.xlsx',
            'status' => JobStatus::Pending,
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        $this->runParse($job);
        $job->refresh();

        $this->assertSame(JobStatus::Parsed, $job->status);
        $this->assertSame(3, $job->stagingRecords()->count());
        $this->assertSame(3, $job->total_rows);
    }

    public function test_static_columns_are_kept_when_columns_are_restricted(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-importer/inputs/static.csv', "reference,name,price\nSKU-1,Moteur,19900\n");

        $config = ImporterConfig::create([
            'name' => 'test-static',
            'config_data' => [
                'primary_sheet' => 'Worksheet',
                'mapping' => [
                    'reference' => ['col' => 'reference'],
                    'name' => ['col' => 'name'],
                    'price_cents' => ['col' => 'price'],
                    // Colonnes statiques : absentes de la grille « colonnes à calculer ».
                    'supplier' => ['default' => 'SOMFY'],
                    'additional_shipping_cost' => ['default' => '0'],
                ],
            ],
        ]);

        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'ai-importer/inputs/static.csv',
            'status' => JobStatus::Pending,
            'import_status' => 'pending',
            'error_policy' => 'ignore',
            'options' => ['columns_to_process' => ['reference', 'name']],
        ]);

        (new ParseFileToStagingJob($job->id))->handle(app(SpreadsheetParser::class), app(ActionPipeline::class));

        $data = (array) $job->stagingRecords()->firstOrFail()->data;
        $this->assertSame('SKU-1', $data['reference']);
        $this->assertSame('SOMFY', $data['supplier']);
        $this->assertSame('0', $data['additional_shipping_cost']);
        $this->assertArrayNotHasKey('price_cents', $data, 'colonne décochée : non calculée');
    }

    public function test_missing_sheet_stops_the_parse_with_a_clear_message(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-importer/inputs/nosheet.csv', "reference,name\nSKU-1,Moteur\n");

        $config = ImporterConfig::create([
            'name' => 'test-nosheet',
            'config_data' => [
                'primary_sheet' => 'Worksheet',
                'join_key' => 'reference',
                'sheets' => ['B03_MEDIA' => ['relation' => 'many', 'join_col' => 'B']],
                'mapping' => ['reference' => ['col' => 'reference']],
            ],
        ]);

        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'ai-importer/inputs/nosheet.csv',
            'status' => JobStatus::Pending,
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        // Pas d'exception : la file ne retente pas un fichier qui ne correspond pas.
        (new ParseFileToStagingJob($job->id))->handle(app(SpreadsheetParser::class), app(ActionPipeline::class));

        $job->refresh();
        $this->assertSame(JobStatus::Error, $job->status);
        $this->assertStringContainsString('B03_MEDIA', (string) $job->error_message);
        $this->assertSame(0, $job->stagingRecords()->count());
    }

    public function test_a_preparation_stopped_before_the_worker_is_not_started(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-importer/inputs/stopped.csv', "reference,name\nSKU-1,Moteur\n");

        $config = ImporterConfig::create([
            'name' => 'test-stopped',
            'config_data' => ['primary_sheet' => 'Worksheet', 'mapping' => ['reference' => ['col' => 'reference']]],
        ]);

        // L'utilisateur a cliqué « Arrêter la préparation » alors que le job attendait en file.
        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'ai-importer/inputs/stopped.csv',
            'status' => JobStatus::Cancelled,
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        (new ParseFileToStagingJob($job->id))->handle(app(SpreadsheetParser::class), app(ActionPipeline::class));

        $this->assertSame(JobStatus::Cancelled, $job->refresh()->status);
        $this->assertSame(0, $job->stagingRecords()->count());
    }

    public function test_named_column_missing_from_headers_is_reported_in_the_console(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-importer/inputs/nocol.csv', "reference,name\nSKU-1,Moteur\n");

        $config = ImporterConfig::create([
            'name' => 'test-nocol',
            'config_data' => [
                'primary_sheet' => 'Worksheet',
                'mapping' => [
                    'reference' => ['col' => 'reference'],
                    'ean' => ['col' => 'GTIN'],
                ],
            ],
        ]);

        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'ai-importer/inputs/nocol.csv',
            'status' => JobStatus::Pending,
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        (new ParseFileToStagingJob($job->id))->handle(app(SpreadsheetParser::class), app(ActionPipeline::class));

        $this->assertSame(JobStatus::Parsed, $job->refresh()->status);
        $this->assertTrue(
            ImportLog::where('import_job_id', $job->id)->where('level', 'warning')->where('message', 'like', '%GTIN%')->exists(),
        );
    }

    public function test_presence_filter_is_applied_before_ai_columns(): void
    {
        Storage::fake('local');
        Http::fake();
        Storage::disk('local')->put('ai-importer/inputs/filter.csv', "reference,name\nDEJA-LA,Moteur connu\nNOUVEAU,Moteur neuf\n");

        // Un produit du fichier existe déjà au catalogue.
        ProductType::firstOrCreate(['name' => 'Test Type']);
        TaxClass::firstOrCreate(['name' => 'Standard'], ['default' => true]);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'exchange_rate' => 1.0, 'default' => true, 'enabled' => true]);
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);
        $seed = ImportJob::create(['input_file_path' => 'n/a', 'status' => 'parsed', 'import_status' => 'pending', 'error_policy' => 'ignore']);
        (new LunarProductWriter)->write(StagingRecord::create([
            'import_job_id' => $seed->id, 'row_number' => 1, 'status' => StagingStatus::Pending,
            'data' => ['reference' => 'DEJA-LA', 'name' => 'Moteur connu'],
        ]));

        $config = ImporterConfig::create([
            'name' => 'test-filter',
            'config_data' => [
                'primary_sheet' => 'Worksheet',
                'mapping' => [
                    // Colonne IA déclarée en premier : elle est tout de même calculée
                    // après les colonnes simples, donc après le filtre.
                    'description' => ['col' => 'name', 'actions' => [['type' => 'llm_transform', 'prompt' => 'Décris']]],
                    'reference' => ['col' => 'reference'],
                    'name' => ['col' => 'name'],
                ],
            ],
        ]);

        $job = ImportJob::create([
            'config_id' => $config->id,
            'input_file_path' => 'ai-importer/inputs/filter.csv',
            'status' => JobStatus::Pending,
            'import_status' => 'pending',
            'error_policy' => 'ignore',
            'options' => ['row_filter' => 'missing_supplier_ref'],
        ]);

        (new ParseFileToStagingJob($job->id))->handle(app(SpreadsheetParser::class), app(ActionPipeline::class));

        $rows = $job->stagingRecords()->orderBy('row_number')->get();
        $known = $rows[0];
        $new = $rows[1];

        // Produit déjà au catalogue : écarté, sa colonne IA n'a pas été calculée.
        $this->assertSame(StagingStatus::Skipped, $known->status);
        $this->assertStringContainsString('filtre', (string) $known->error_message);
        $this->assertArrayNotHasKey('description', (array) $known->data);

        // Nouveau produit : préparé en entier.
        $this->assertSame(StagingStatus::Pending, $new->status);
        $this->assertArrayHasKey('description', (array) $new->data);

        Http::assertNothingSent();
    }
}
