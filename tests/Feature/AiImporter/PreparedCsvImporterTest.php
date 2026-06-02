<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Services\PreparedCsvImporter;
use Tests\TestCase;

/**
 * Couvre l'import direct d'un CSV déjà préparé en staging (sans config) :
 * séparateur « ; », BOM UTF-8, 1ʳᵉ ligne = en-têtes → clés du `data`.
 */
class PreparedCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_prepared_csv_rows_into_staging_without_config(): void
    {
        Storage::fake('local');

        $csv = "reference;name;price_cents\nSKU-1;Moteur Somfy;19900\nSKU-2;Rail alu;4500\n";
        Storage::disk('local')->put('ai-importer/inputs/prepared.csv', $csv);

        $job = (new PreparedCsvImporter)->import('ai-importer/inputs/prepared.csv', 'Mon import');

        $this->assertNull($job->config_id);
        $this->assertSame(JobStatus::Parsed, $job->status);
        $this->assertSame(ImportStatus::Pending, $job->import_status);
        $this->assertSame(2, $job->total_rows);
        $this->assertSame(2, $job->staging_count);
        $this->assertSame(2, $job->stagingRecords()->count());
        $this->assertSame('Mon import', $job->options['import_name']);

        $first = $job->stagingRecords()->orderBy('row_number')->first();
        $this->assertNotNull($first);
        $this->assertSame(1, $first->row_number);
        $this->assertSame(StagingStatus::Pending, $first->status);

        $data = (array) $first->data;
        $this->assertSame('SKU-1', $data['reference']);
        $this->assertSame('Moteur Somfy', $data['name']);
        $this->assertSame('19900', $data['price_cents']);
    }

    public function test_strips_utf8_bom_from_first_header_and_defaults_name_to_filename(): void
    {
        Storage::fake('local');

        $csv = "\xEF\xBB\xBFreference;name\nSKU-9;Produit\n";
        Storage::disk('local')->put('ai-importer/inputs/bom.csv', $csv);

        $job = (new PreparedCsvImporter)->import('ai-importer/inputs/bom.csv');

        $this->assertSame('bom.csv', $job->options['import_name']);

        $data = (array) $job->stagingRecords()->first()->data;
        $this->assertArrayHasKey('reference', $data);
        $this->assertArrayNotHasKey("\xEF\xBB\xBFreference", $data);
        $this->assertSame('SKU-9', $data['reference']);
    }

    public function test_header_only_file_creates_job_with_zero_staging(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('ai-importer/inputs/empty.csv', "reference;name\n");

        $job = (new PreparedCsvImporter)->import('ai-importer/inputs/empty.csv');

        $this->assertSame(0, $job->staging_count);
        $this->assertSame(0, $job->total_rows);
        $this->assertSame(0, $job->stagingRecords()->count());
    }
}
