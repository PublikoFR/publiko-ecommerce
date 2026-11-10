<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Filament\Resources\ImportJobResource\Pages\CreateImportJob;
use Pko\AiImporter\Filament\Resources\ImportJobResource\Pages\ListImportJobs;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Support\ConfigDiagnostics;
use Tests\TestCase;

/**
 * « Nouvel import » : assistant en 2 étapes, contrôle du fichier contre la
 * configuration, lancement de la préparation. Aucun appel réseau.
 */
class CreateImportJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
        config(['ai-importer.queues.connection' => 'database-long']);

        $this->actingAs(Staff::create([
            'first_name' => 'Test', 'last_name' => 'Admin', 'email' => 'staff@example.test', 'password' => 'secret123', 'admin' => true,
        ]), 'staff');
    }

    /** @param  array<string, mixed>  $mapping */
    private function config(array $mapping = []): ImporterConfig
    {
        return ImporterConfig::create([
            'name' => 'CSV-TEST',
            'config_data' => [
                'primary_sheet' => 'Worksheet',
                'mapping' => $mapping ?: [
                    'reference' => ['col' => 'reference'],
                    'name' => ['col' => 'name'],
                    'price_cents' => ['col' => 'price'],
                    'description' => ['col' => 'name', 'actions' => [['type' => 'llm_transform', 'prompt' => 'Décris']]],
                    'supplier' => ['default' => 'SOMFY'],
                ],
            ],
        ]);
    }

    /**
     * Renseigne le formulaire champ par champ via `set()`, équivalent à la
     * saisie. Historique : `fillForm()` ne faisait rien tant que phpunit.xml ne
     * forçait pas APP_ENV=testing (APP_ENV=local réel dans le conteneur de dev) ;
     * c'est corrigé, `set()` est conservé (cf. docs/workflow.md).
     *
     * @param  array<string, mixed>  $state
     */
    private function fill(Testable $page, array $state): Testable
    {
        foreach ($state as $key => $value) {
            $page->set('data.'.$key, $value);
        }

        return $page;
    }

    private function csv(string $content = "reference,name,price\nSKU-1,Moteur,19900\nSKU-2,Rail,4500\n"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('Tarif Somfy 2026.csv', $content);
    }

    public function test_wizard_opens_with_the_configuration_passed_in_the_url(): void
    {
        $config = $this->config();

        Livewire::withQueryParams(['config' => $config->id])
            ->test(CreateImportJob::class)
            ->assertOk()
            ->assertFormSet(['config_id' => $config->id])
            // Toutes les colonnes calculables sont cochées, pas la colonne à valeur fixe.
            ->assertFormSet(fn (array $state): bool => $state['options']['columns_to_process'] === ['description', 'name', 'price_cents', 'reference'])
            ->assertSee('Contrôle de la configuration');
    }

    public function test_file_is_checked_against_the_configuration(): void
    {
        $config = $this->config();
        Storage::disk('local')->put('ai-importer/inputs/ok.csv', "reference,name,price\nSKU-1,Moteur,19900\n");
        Storage::disk('local')->put('ai-importer/inputs/ko.csv', "ref,libelle\nSKU-1,Moteur\n");

        $ok = ImportJobResource::preflight($config->id, 'ai-importer/inputs/ok.csv');
        $this->assertTrue($ok['file']);
        $this->assertSame(['Worksheet' => 1], $ok['sheets']);
        $this->assertSame([], $ok['findings']);

        $ko = ImportJobResource::preflight($config->id, 'ai-importer/inputs/ko.csv');
        $this->assertStringContainsString('reference', implode(' ', array_column($ko['findings'], 'message')));
        // Colonnes de données absentes : avertissement, pas un blocage.
        $this->assertFalse(ConfigDiagnostics::hasErrors($ko['findings']));
    }

    public function test_a_blocking_error_keeps_the_user_on_the_first_step(): void
    {
        // Aucune colonne d'identification : chaque ligne serait rejetée à l'import.
        $config = $this->config(['name' => ['col' => 'name']]);

        $this->fill(Livewire::test(CreateImportJob::class), ['config_id' => $config->id, 'input_file_path' => $this->csv()])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(1)
            ->assertNotified('Le contrôle signale une erreur bloquante');

        $this->assertSame(0, ImportJob::count());
    }

    public function test_creating_an_import_starts_the_preparation_and_opens_it(): void
    {
        $config = $this->config();

        $page = $this->fill(Livewire::test(CreateImportJob::class), ['config_id' => $config->id, 'input_file_path' => $this->csv()])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(2);

        $this->fill($page, [
            'row_limit' => 10,
            // Colonne IA décochée : aucun contrôle ni appel IA.
            'options.columns_to_process' => ['reference', 'name', 'price_cents'],
        ])
            ->call('create')
            ->assertHasNoFormErrors();

        $job = ImportJob::query()->sole();
        $page->assertRedirect(ImportJobResource::getUrl('view', ['record' => $job]));

        $this->assertSame($config->id, $job->config_id);
        $this->assertSame(10, $job->row_limit);
        $this->assertSame(['reference', 'name', 'price_cents'], (array) $job->options['columns_to_process']);
        $this->assertNotNull($job->created_by_id, 'le créateur reçoit les notifications de fin');
        // Nom de fichier lisible, celui d'origine.
        $this->assertStringContainsString('tarif-somfy-2026', (string) $job->input_file_path);
        Storage::disk('local')->assertExists($job->input_file_path);

        // Parti tout de suite, sorti de l'état que le planificateur ramasse.
        $this->assertSame(JobStatus::Parsing, $job->status);
        Queue::assertPushed(ParseFileToStagingJob::class, fn (ParseFileToStagingJob $queued): bool => $queued->importJobId === $job->id);
    }

    public function test_an_import_needs_at_least_one_column(): void
    {
        $config = $this->config();

        $page = $this->fill(Livewire::test(CreateImportJob::class), ['config_id' => $config->id, 'input_file_path' => $this->csv()])
            ->goToNextWizardStep();

        $this->fill($page, ['options.columns_to_process' => []])
            ->call('create')
            ->assertNotified('Aucune colonne à calculer');

        $this->assertSame(0, ImportJob::count());
    }

    public function test_columns_summary_counts_ai_calls_and_lists_useful_fixed_values(): void
    {
        $config = $this->config([
            'reference' => ['col' => 'reference'],
            'description' => ['col' => 'name', 'actions' => [['type' => 'llm_transform', 'prompt' => 'x']]],
            'features' => ['col' => 'name', 'actions' => [['type' => 'llm_transform', 'prompt' => 'y']]],
            'supplier' => ['default' => 'SOMFY'],
            'unity' => ['default' => 'unité'],
        ]);
        Storage::disk('local')->put('ai-importer/inputs/f.csv', "reference,name\n".str_repeat("SKU,Moteur\n", 40));

        $summary = ImportJobResource::columnsSummary($config->id, ['reference', 'description', 'features'], 25, 'ai-importer/inputs/f.csv');

        $this->assertSame(['description', 'features'], $summary['ai']);
        $this->assertSame(25, $summary['rows'], 'limité par le nombre de lignes demandé');
        $this->assertSame(50, $summary['calls']);
        // `unity` n'a aucun champ au catalogue : inutile de l'annoncer.
        $this->assertSame(['supplier' => 'SOMFY'], $summary['static']);
    }

    public function test_imports_list_shows_one_readable_step_per_import(): void
    {
        $config = $this->config();
        $review = ImportJob::create([
            'config_id' => $config->id, 'input_file_path' => 'ai-importer/inputs/tarif.csv',
            'status' => 'parsed', 'import_status' => 'pending', 'error_policy' => 'ignore', 'staging_count' => 12,
        ]);
        $done = ImportJob::create([
            'config_id' => $config->id, 'input_file_path' => 'ai-importer/inputs/tarif.csv',
            'status' => 'parsed', 'import_status' => 'imported', 'error_policy' => 'ignore',
        ]);

        Livewire::test(ListImportJobs::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$review, $done])
            ->assertSee('À vérifier')
            ->assertSee('Terminé')
            ->assertSee('CSV-TEST')
            ->assertActionExists('importPreparation')
            ->assertActionExists('runScheduler');
    }
}
