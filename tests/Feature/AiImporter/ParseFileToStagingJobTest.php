<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ActionRegistry;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Services\ActionPipeline;
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
}
