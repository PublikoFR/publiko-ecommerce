<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Lunar\Admin\Models\Staff;
use Pko\AiImporter\Enums\FlowState;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Http\Middleware\RaiseStaffUploadLimit;
use Pko\AiImporter\Jobs\ImportStagingToLunarJob;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Notifications\ImportJobNotifier;
use Pko\AiImporter\Support\ImportFlow;
use Tests\TestCase;

/**
 * Transitions d'un import déclenchées depuis l'interface : départ immédiat
 * quand un worker traite la file, repli sur le planificateur en `sync`.
 */
class ImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private function job(array $attributes = []): ImportJob
    {
        return ImportJob::create($attributes + [
            'input_file_path' => 'n/a',
            'status' => 'pending',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);
    }

    private function background(bool $enabled): void
    {
        Queue::fake();
        config(['ai-importer.queues.connection' => $enabled ? 'database-long' : null, 'queue.default' => 'sync']);
    }

    public function test_parse_starts_at_once_when_a_worker_handles_the_queue(): void
    {
        $this->background(true);
        $job = $this->job();

        $this->assertTrue(ImportFlow::startParse($job));

        // Sorti de l'état éligible avant le dispatch : le planificateur ne le relancera pas.
        $this->assertSame(JobStatus::Parsing, $job->refresh()->status);
        Queue::assertPushed(ParseFileToStagingJob::class, 1);
    }

    public function test_parse_is_left_to_the_scheduler_on_a_sync_queue(): void
    {
        $this->background(false);
        $job = $this->job(['status' => 'error', 'error_message' => 'Feuille absente']);

        $this->assertFalse(ImportFlow::startParse($job));

        $job->refresh();
        $this->assertSame(JobStatus::Pending, $job->status);
        $this->assertNull($job->error_message);
        Queue::assertNothingPushed();
    }

    public function test_import_starts_at_once_or_is_scheduled(): void
    {
        $this->background(true);

        $now = $this->job(['status' => 'parsed']);
        $this->assertSame('now', ImportFlow::startImport($now));
        $this->assertSame(ImportStatus::Queued, $now->refresh()->import_status);

        $later = $this->job(['status' => 'parsed']);
        $this->assertSame('scheduled', ImportFlow::startImport($later, now()->addHour()));
        $this->assertSame(ImportStatus::Scheduled, $later->refresh()->import_status);

        Queue::assertPushed(ImportStagingToLunarJob::class, 1);
    }

    public function test_import_on_a_sync_queue_waits_for_the_scheduler(): void
    {
        $this->background(false);
        $job = $this->job(['status' => 'parsed']);

        $this->assertSame('soon', ImportFlow::startImport($job));

        $job->refresh();
        $this->assertSame(ImportStatus::Scheduled, $job->import_status);
        $this->assertTrue($job->scheduled_at->isPast() || $job->scheduled_at->isCurrentMinute());
        Queue::assertNothingPushed();
    }

    public function test_reimport_after_a_rollback_takes_a_fresh_backup(): void
    {
        $this->background(true);
        $job = $this->job([
            'status' => 'parsed', 'import_status' => 'rolled_back',
            'backup_path' => 'ai-importer/backups/old.json.gz', 'rollback_completed' => true,
            'imported_count' => 12, 'error_count' => 3,
        ]);

        ImportFlow::startImport($job);

        $job->refresh();
        $this->assertNull($job->backup_path);
        $this->assertFalse($job->rollback_completed);
        $this->assertSame(0, $job->imported_count);
        $this->assertSame(0, $job->error_count);
    }

    public function test_retry_puts_only_error_rows_back(): void
    {
        $job = $this->job(['status' => 'parsed', 'import_status' => 'imported']);
        $row = fn (StagingStatus $status, int $n): StagingRecord => StagingRecord::create([
            'import_job_id' => $job->id, 'row_number' => $n, 'data' => ['reference' => 'R'.$n], 'status' => $status,
            'error_message' => $status === StagingStatus::Error ? 'reference manquante' : null,
        ]);
        $error = $row(StagingStatus::Error, 1);
        $created = $row(StagingStatus::Created, 2);
        $skipped = $row(StagingStatus::Skipped, 3);

        $this->assertSame(1, ImportFlow::retryErrorRows($job));

        $this->assertSame(StagingStatus::Pending, $error->refresh()->status);
        $this->assertNull($error->error_message);
        $this->assertSame(StagingStatus::Created, $created->refresh()->status);
        $this->assertSame(StagingStatus::Skipped, $skipped->refresh()->status);
        $this->assertSame(1, $job->remainingRows());
    }

    public function test_flow_state_reads_as_one_step_for_every_status_pair(): void
    {
        $expect = [
            ['pending', 'pending', FlowState::Waiting, 1],
            ['parsing', 'pending', FlowState::Preparing, 1],
            ['error', 'pending', FlowState::PrepareFailed, 1],
            ['cancelled', 'pending', FlowState::Cancelled, 1],
            ['parsed', 'pending', FlowState::Review, 2],
            ['parsed', 'rolled_back', FlowState::RolledBack, 2],
            ['parsed', 'scheduled', FlowState::Scheduled, 3],
            ['parsed', 'queued', FlowState::Importing, 3],
            ['parsed', 'importing', FlowState::Importing, 3],
            ['parsed', 'error', FlowState::ImportFailed, 3],
            ['parsed', 'imported', FlowState::Done, 4],
        ];

        foreach ($expect as [$status, $import, $state, $step]) {
            $job = $this->job(['status' => $status, 'import_status' => $import]);
            $this->assertSame($state, $job->flowState(), "{$status}/{$import}");
            $this->assertSame($step, $job->flowState()->step(), "{$status}/{$import}");
        }
    }

    public function test_duplicate_references_are_reported_before_import(): void
    {
        $job = $this->job(['status' => 'parsed']);
        foreach (['SOM', 'SOM', 'SOM', 'SOM1001547', 'SOM1001550'] as $n => $reference) {
            StagingRecord::create([
                'import_job_id' => $job->id, 'row_number' => $n + 1,
                'data' => ['reference' => $reference, 'name' => 'Produit'], 'status' => StagingStatus::Pending,
            ]);
        }

        $this->assertSame(['SOM' => 3], $job->duplicateReferences());
    }

    public function test_upload_limit_is_raised_for_staff_only(): void
    {
        config(['ai-importer.upload.max_size_kb' => 102400, 'livewire.temporary_file_upload.rules' => null]);
        $middleware = new RaiseStaffUploadLimit;
        $next = fn () => response('ok');

        // Visiteur du storefront : limite Livewire par défaut conservée.
        $middleware->handle(Request::create('/livewire/upload-file', 'POST'), $next);
        $this->assertNull(config('livewire.temporary_file_upload.rules'));

        $this->actingAs(Staff::create([
            'first_name' => 'Test', 'last_name' => 'Admin', 'email' => 'staff@example.test', 'password' => 'secret123', 'admin' => true,
        ]), 'staff');

        $middleware->handle(Request::create('/livewire/upload-file', 'POST'), $next);
        $this->assertSame(['required', 'file', 'max:102400'], config('livewire.temporary_file_upload.rules'));
    }

    public function test_end_of_import_notification_is_skipped_without_a_notifications_table(): void
    {
        Notification::fake();
        ImportJobNotifier::flush();
        $this->assertFalse(Schema::hasTable('notifications'), 'ce projet n\'active pas les notifications en base');

        $staff = Staff::create([
            'first_name' => 'Test', 'last_name' => 'Admin', 'email' => 'notif@example.test', 'password' => 'secret123', 'admin' => true,
        ]);
        $job = $this->job(['status' => 'parsed', 'created_by_id' => $staff->id]);

        ImportJobNotifier::parseCompleted($job);
        ImportJobNotifier::importCompleted($job);

        // Sinon : un job en échec dans la file à chaque fin d'import.
        Notification::assertNothingSent();
    }
}
