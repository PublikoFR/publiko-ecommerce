<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Services\ActionPipeline;
use Pko\AiImporter\Services\SpreadsheetParser;
use Tests\TestCase;

class ParseFileToStagingJobTest extends TestCase
{
    use RefreshDatabase;

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
}
