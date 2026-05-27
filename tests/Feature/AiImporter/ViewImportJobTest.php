<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Filament\Resources\ImportJobResource\Pages\ViewImportJob;
use Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers\StagingRecordsRelationManager;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\StagingRecord;
use Tests\TestCase;

/**
 * Couvre la page « Aperçu & Import » V4 : compteurs de staging (stat cards)
 * et édition des lignes staging (relation manager).
 */
class ViewImportJobTest extends TestCase
{
    use RefreshDatabase;

    private function job(): ImportJob
    {
        return ImportJob::create([
            'input_file_path' => 'n/a',
            'status' => 'parsed',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);
    }

    private function record(ImportJob $job, StagingStatus $status, int $row): StagingRecord
    {
        return StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => $row,
            'data' => ['reference' => 'SKU'.$row, 'name' => 'Produit '.$row, 'price_cents' => 1000 * $row],
            'status' => $status,
        ]);
    }

    private function actAsAdmin(): void
    {
        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'staff@example.test',
            'password' => 'secret123',
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');
    }

    public function test_staging_status_counts_aggregates_by_bucket(): void
    {
        $job = $this->job();

        $this->record($job, StagingStatus::Pending, 1);
        $this->record($job, StagingStatus::Validated, 2);
        $this->record($job, StagingStatus::Imported, 3);
        $this->record($job, StagingStatus::Updated, 4);
        $this->record($job, StagingStatus::Warning, 5);
        $this->record($job, StagingStatus::Error, 6);
        $this->record($job, StagingStatus::Skipped, 7);

        $counts = $job->stagingStatusCounts();

        $this->assertSame(7, $counts['total']);
        $this->assertSame(2, $counts['pending']);   // pending + validated
        $this->assertSame(2, $counts['imported']);  // imported + updated (+ created)
        $this->assertSame(1, $counts['warning']);
        $this->assertSame(1, $counts['error']);
        $this->assertSame(1, $counts['skipped']);
    }

    public function test_staging_counts_empty_job(): void
    {
        $counts = $this->job()->stagingStatusCounts();

        $this->assertSame(0, $counts['total']);
        $this->assertSame(0, $counts['pending']);
    }

    public function test_staging_record_can_be_edited_via_relation_manager(): void
    {
        $this->actAsAdmin();

        $job = $this->job();
        $staging = $this->record($job, StagingStatus::Pending, 1);

        Livewire::test(StagingRecordsRelationManager::class, [
            'ownerRecord' => $job,
            'pageClass' => ViewImportJob::class,
        ])
            ->callTableAction('edit', $staging, data: [
                'status' => StagingStatus::Validated->value,
                'data' => json_encode(['reference' => 'SKU1', 'name' => 'Renommé', 'price_cents' => 2500]),
            ])
            ->assertHasNoTableActionErrors();

        $staging->refresh();

        $this->assertSame(StagingStatus::Validated, $staging->status);
        $this->assertSame('Renommé', ((array) $staging->data)['name']);
        $this->assertSame(2500, ((array) $staging->data)['price_cents']);
    }
}
