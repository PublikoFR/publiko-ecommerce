<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Jobs\ImportStagingToLunarJob;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImporterConfig;
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

    public function test_jobs_are_dispatched_on_dedicated_queues_and_long_connection(): void
    {
        config(['ai-importer.queues.connection' => 'database-long']);
        Queue::fake();

        $this->dueJob();
        $pending = ImportJob::create([
            'input_file_path' => 'n/a',
            'config_id' => ImporterConfig::create([
                'name' => 'Cfg queue',
                'config_data' => ['primary_sheet' => 'S', 'mapping' => []],
            ])->id,
            'status' => 'pending',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        $this->artisan('ai-importer:run-scheduled')->assertSuccessful();

        Queue::assertPushed(ImportStagingToLunarJob::class, fn (ImportStagingToLunarJob $job): bool => $job->queue === 'ai-importer-import'
            && $job->connection === 'database-long');
        Queue::assertPushed(ParseFileToStagingJob::class, fn (ParseFileToStagingJob $job): bool => $job->importJobId === $pending->id
            && $job->queue === 'ai-importer-parse'
            && $job->connection === 'database-long');
    }

    public function test_long_connections_outlive_job_timeouts(): void
    {
        $timeout = (new ImportStagingToLunarJob(1))->timeout;

        $this->assertGreaterThan($timeout, config('queue.connections.database-long.retry_after'));
        $this->assertGreaterThan($timeout, config('queue.connections.redis-long.retry_after'));
    }

    public function test_dedicated_worker_is_scheduled_apart_from_default_worker(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn (Event $event): string => (string) $event->command)
            ->filter(fn (string $command): bool => str_contains($command, 'queue:work'));

        $dedicated = $commands->filter(fn (string $c): bool => str_contains($c, '--queue=ai-importer-parse,ai-importer-import'));
        $this->assertCount(1, $dedicated, 'Worker dédié aux imports absent du planning.');

        // Le worker par défaut n'écoute PAS les files d'import.
        $default = $commands->reject(fn (string $c): bool => str_contains($c, 'ai-importer'));
        $this->assertCount(1, $default);
    }
}
