<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use PHPUnit\Framework\Attributes\DataProvider;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\LogPhase;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Filament\Resources\ImportJobResource\Pages\ViewImportJob;
use Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers\StagingRecordsRelationManager;
use Pko\AiImporter\Jobs\ImportStagingToLunarJob;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Tests\TestCase;

/**
 * Écran d'un import : compteurs de lignes, message et actions de chaque étape
 * du parcours, vérification / correction des lignes préparées, lancement.
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

    private function stagingTable(ImportJob $job): Testable
    {
        return Livewire::test(StagingRecordsRelationManager::class, [
            'ownerRecord' => $job,
            'pageClass' => ViewImportJob::class,
        ]);
    }

    public function test_staging_record_can_be_edited_via_relation_manager(): void
    {
        $this->actAsAdmin();

        $job = $this->job();
        $staging = $this->record($job, StagingStatus::Pending, 1);

        $this->stagingTable($job)
            ->callTableAction('edit', $staging, data: [
                // Fiche champ par champ : un field `data.<clé>` par colonne préparée.
                'data' => ['reference' => 'SKU1', 'name' => 'Renommé', 'price_cents' => 2500],
            ])
            ->assertHasNoTableActionErrors();

        $staging->refresh();

        $this->assertSame(StagingStatus::Pending, $staging->status);
        $this->assertSame('Renommé', ((array) $staging->data)['name']);
        $this->assertSame(2500, ((array) $staging->data)['price_cents']);
    }

    public function test_correcting_an_error_row_puts_it_back_to_import(): void
    {
        $this->actAsAdmin();

        $job = $this->job();
        $staging = $this->record($job, StagingStatus::Error, 1);
        $staging->update(['error_message' => 'reference manquante']);

        $this->stagingTable($job)
            ->callTableAction('edit', $staging, data: ['data' => ['reference' => 'SKU-OK', 'name' => 'Produit', 'price_cents' => 1000]])
            ->assertHasNoTableActionErrors();

        $staging->refresh();
        $this->assertSame(StagingStatus::Pending, $staging->status);
        $this->assertNull($staging->error_message);
    }

    public function test_structured_values_survive_an_edit(): void
    {
        $this->actAsAdmin();

        $job = $this->job();
        $staging = StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 1,
            'data' => ['reference' => 'SKU1', 'name' => 'Produit', 'features' => ['gamme' => ['io', 'rts']], 'hors_formulaire' => 'x'],
            'status' => StagingStatus::Pending,
        ]);

        $this->stagingTable($job)
            ->callTableAction('edit', $staging, data: ['data' => ['name' => 'Renommé']])
            ->assertHasNoTableActionErrors();

        $data = $staging->refresh()->data->getArrayCopy();
        $this->assertSame('Renommé', $data['name']);
        // Le hash de caractéristiques, édité en JSON, reste un tableau.
        $this->assertSame(['gamme' => ['io', 'rts']], $data['features']);
        $this->assertSame('x', $data['hors_formulaire']);
    }

    public function test_a_row_can_be_set_aside_and_restored(): void
    {
        $this->actAsAdmin();

        $job = $this->job();
        $staging = $this->record($job, StagingStatus::Pending, 1);

        $this->stagingTable($job)->callTableAction('skip', $staging);
        $this->assertSame(StagingStatus::Skipped, $staging->refresh()->status);

        $this->stagingTable($job)->callTableAction('restore', $staging);
        $this->assertSame(StagingStatus::Pending, $staging->refresh()->status);
        $this->assertNull($staging->error_message);
    }

    public function test_tabs_split_rows_by_situation(): void
    {
        $this->actAsAdmin();

        $job = $this->job();
        $pending = $this->record($job, StagingStatus::Pending, 1);
        $error = $this->record($job, StagingStatus::Error, 2);
        $skipped = $this->record($job, StagingStatus::Skipped, 3);
        $created = $this->record($job, StagingStatus::Created, 4);

        $this->stagingTable($job)
            ->assertCanSeeTableRecords([$pending, $error, $skipped, $created])
            ->set('activeTab', 'to_import')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$error, $skipped, $created])
            ->set('activeTab', 'errors')
            ->assertCanSeeTableRecords([$error])
            ->assertCanNotSeeTableRecords([$pending])
            ->set('activeTab', 'imported')
            ->assertCanSeeTableRecords([$created])
            ->set('activeTab', 'skipped')
            ->assertCanSeeTableRecords([$skipped]);
    }

    public function test_price_preview_includes_ecotax_like_the_import_will(): void
    {
        $this->actAsAdmin();

        $job = $this->job();
        StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 1,
            'data' => ['reference' => 'SOM1', 'name' => 'Moteur', 'price_tex' => '186,52', 'ecotax' => '0,16', 'manufacturer' => 'SOMFY'],
            'status' => StagingStatus::Pending,
        ]);

        $this->stagingTable($job)
            ->assertSee('186,68 €')
            ->assertSee('dont éco 0,16 €')
            ->assertSee('SOMFY');
    }

    public function test_rows_are_locked_while_an_import_is_running(): void
    {
        $this->actAsAdmin();

        $job = $this->job();
        $job->update(['import_status' => 'importing']);
        $staging = $this->record($job, StagingStatus::Pending, 1);

        $this->stagingTable($job)
            ->assertTableActionHidden('edit', $staging)
            ->assertTableActionHidden('skip', $staging);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: array<int, string>, 4: array<int, string>}>
     */
    public static function flowStates(): array
    {
        return [
            'file d\'attente' => ['pending', 'pending', 'Préparation en file d\'attente', ['cancel'], ['launchImport', 'rollback']],
            'préparation' => ['parsing', 'pending', 'Préparation en cours', ['cancel'], ['launchImport']],
            'préparation en échec' => ['error', 'pending', 'La préparation s\'est arrêtée', ['resumeParse'], ['launchImport', 'cancel']],
            'à vérifier' => ['parsed', 'pending', 'ligne à vérifier', ['launchImport', 'editOptions'], ['resumeParse', 'cancel', 'rollback']],
            'programmé' => ['parsed', 'scheduled', 'Import', ['cancelSchedule'], ['launchImport']],
            'import en cours' => ['parsed', 'importing', 'Import en cours', [], ['launchImport', 'cancelSchedule', 'rollback']],
            'terminé' => ['parsed', 'imported', 'Import terminé', [], ['resumeParse', 'cancel']],
            'annulé' => ['parsed', 'rolled_back', 'Import annulé', ['launchImport'], ['rollback']],
        ];
    }

    /**
     * @param  array<int, string>  $visible
     * @param  array<int, string>  $hidden
     */
    #[DataProvider('flowStates')]
    public function test_each_step_shows_its_message_and_only_its_actions(string $status, string $importStatus, string $message, array $visible, array $hidden): void
    {
        $this->actAsAdmin();

        $job = $this->job();
        $job->update([
            'status' => $status,
            'import_status' => $importStatus,
            'error_message' => $status === 'error' ? 'Feuille absente' : null,
            // Une préparation ne se relance qu'avec sa configuration.
            'config_id' => ImporterConfig::create(['name' => 'cfg', 'config_data' => ['mapping' => []]])->id,
        ]);
        $this->record($job, in_array($importStatus, ['imported'], true) ? StagingStatus::Created : StagingStatus::Pending, 1);

        $page = Livewire::test(ViewImportJob::class, ['record' => $job->getRouteKey()])
            ->assertOk()
            ->assertSee($message)
            ->assertSee('Vérification');

        foreach ($visible as $action) {
            $page->assertActionVisible($action);
        }
        foreach ($hidden as $action) {
            $page->assertActionHidden($action);
        }
    }

    public function test_import_action_saves_settings_and_starts_the_import(): void
    {
        Queue::fake();
        config(['ai-importer.queues.connection' => 'database-long']);
        $this->actAsAdmin();

        $job = $this->job();
        $this->record($job, StagingStatus::Pending, 1);

        Livewire::test(ViewImportJob::class, ['record' => $job->getRouteKey()])
            ->callAction('launchImport', data: [
                'update_mode' => 'price',
                'row_filter' => 'existing_supplier_ref',
                'join_column' => 'ean',
                'limit_columns' => true,
                'columns_to_import' => ['price_cents'],
                'error_policy' => 'stop',
                'when' => 'now',
            ])
            ->assertHasNoActionErrors();

        $job->refresh();
        $this->assertSame('price', $job->options['update_mode']);
        $this->assertSame('existing_supplier_ref', $job->options['row_filter']);
        $this->assertSame('ean', $job->options['join_column']);
        $this->assertSame(['price_cents'], (array) $job->options['columns_to_import']);
        $this->assertSame('stop', $job->error_policy->value);
        $this->assertSame(ImportStatus::Queued, $job->import_status);
        Queue::assertPushed(ImportStagingToLunarJob::class, 1);
    }

    public function test_import_can_be_scheduled_for_later_without_dispatch(): void
    {
        Queue::fake();
        config(['ai-importer.queues.connection' => 'database-long']);
        $this->actAsAdmin();

        $job = $this->job();
        $this->record($job, StagingStatus::Pending, 1);
        $later = now()->addDay()->startOfMinute();

        Livewire::test(ViewImportJob::class, ['record' => $job->getRouteKey()])
            ->callAction('launchImport', data: [
                'update_mode' => 'all', 'row_filter' => 'all', 'join_column' => 'reference',
                'limit_columns' => false, 'error_policy' => 'ignore',
                'when' => 'later', 'scheduled_at' => $later->toDateTimeString(),
            ])
            ->assertHasNoActionErrors();

        $job->refresh();
        $this->assertSame(ImportStatus::Scheduled, $job->import_status);
        $this->assertTrue($later->equalTo($job->scheduled_at));
        $this->assertSame([], (array) $job->options['columns_to_import'], 'pas de limitation = tous les champs');
        Queue::assertNothingPushed();
    }

    public function test_error_rows_can_be_retried_after_an_import(): void
    {
        $this->actAsAdmin();

        $job = $this->job();
        $job->update(['import_status' => 'imported']);
        $error = $this->record($job, StagingStatus::Error, 1);
        $done = $this->record($job, StagingStatus::Created, 2);

        Livewire::test(ViewImportJob::class, ['record' => $job->getRouteKey()])
            ->assertActionHidden('launchImport')
            ->callAction('retryErrors')
            ->assertActionVisible('launchImport');

        $this->assertSame(StagingStatus::Pending, $error->refresh()->status);
        $this->assertSame(StagingStatus::Created, $done->refresh()->status);
    }

    public function test_console_splits_parse_and_import_logs(): void
    {
        $this->actAsAdmin();
        $job = $this->job();

        $log = fn (?LogPhase $phase, string $message) => ImportLog::create([
            'import_job_id' => $job->id,
            'phase' => $phase,
            'level' => LogLevel::Info,
            'message' => $message,
            'created_at' => now(),
        ]);
        $log(LogPhase::Parse, 'log-de-preparation');
        $log(null, 'log-historique-sans-phase');
        $log(LogPhase::Import, 'log-d-import');

        $this->assertSame(
            ['log-de-preparation', 'log-historique-sans-phase'],
            ViewImportJob::phaseLogs($job, LogPhase::Parse)->orderBy('id')->pluck('message')->all(),
        );
        $this->assertSame(['log-d-import'], ViewImportJob::phaseLogs($job, LogPhase::Import)->pluck('message')->all());

        // La console « En direct » n'est affichée que pendant un traitement (ou après
        // un arrêt sur erreur) ; le reste du temps, l'onglet « Journal détaillé » suffit.
        Livewire::test(ViewImportJob::class, ['record' => $job->getRouteKey()])
            ->assertOk()
            ->assertDontSee('log-de-preparation');

        $job->update(['status' => 'parsing']);

        Livewire::test(ViewImportJob::class, ['record' => $job->getRouteKey()])
            ->assertOk()
            ->assertSee('Préparation')
            ->assertSee('log-de-preparation')
            ->assertSee('log-d-import');
    }
}
