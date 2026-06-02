<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Jobs\ImportStagingToLunarJob;
use Pko\AiImporter\Models\ImportJob;
use Tests\TestCase;

/**
 * Garantit l'anti double-dispatch : deux ticks cron consécutifs ne doivent
 * dispatcher qu'une seule fois le même import programmé, même si le worker
 * queue n'a pas encore consommé le job (status reste 'parsed').
 */
class RunScheduledImportsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function dueJob(): ImportJob
    {
        return ImportJob::create([
            'input_file_path' => 'n/a',
            'status' => 'parsed',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
            'scheduled_at' => now()->subMinute(),
        ]);
    }

    public function test_due_job_is_dispatched_only_once_across_two_runs(): void
    {
        Queue::fake();

        $job = $this->dueJob();

        $this->artisan('ai-importer:run-scheduled')->assertSuccessful();

        // Le worker n'a pas encore tourné : status toujours 'parsed'.
        $job->refresh();
        $this->assertSame(ImportStatus::Queued, $job->import_status);

        // Second tick cron : le job ne doit plus être éligible.
        $this->artisan('ai-importer:run-scheduled')->assertSuccessful();

        Queue::assertPushed(ImportStagingToLunarJob::class, 1);
    }

    public function test_dry_run_does_not_dispatch_nor_mutate_status(): void
    {
        Queue::fake();

        $job = $this->dueJob();

        $this->artisan('ai-importer:run-scheduled', ['--dry' => true])->assertSuccessful();

        $job->refresh();
        $this->assertSame(ImportStatus::Pending, $job->import_status);
        Queue::assertNothingPushed();
    }

    public function test_unscheduled_job_is_ignored(): void
    {
        Queue::fake();

        ImportJob::create([
            'input_file_path' => 'n/a',
            'status' => 'parsed',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
            'scheduled_at' => null,
        ]);

        $this->artisan('ai-importer:run-scheduled')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
