<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Services\PreparedCsvImporter;

class CreateImportJob extends CreateRecord
{
    protected static string $resource = ImportJobResource::class;

    public function getTitle(): string
    {
        return 'Préparer un fichier';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('newConfiguration')
                ->label('Nouvelle configuration')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->url(ImporterConfigResource::getUrl('create')),

            Actions\Action::make('importJson')
                ->label('Importer JSON')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Importer une configuration JSON')
                ->modalDescription('Laissez le nom vide pour utiliser le nom du fichier.')
                ->form([
                    Forms\Components\TextInput::make('config_name')
                        ->label('Nom de la configuration')
                        ->placeholder('Optionnel — défaut : nom du fichier')
                        ->maxLength(128),
                    Forms\Components\FileUpload::make('config_file')
                        ->label('Fichier JSON')
                        ->acceptedFileTypes(['application/json', 'text/json'])
                        ->disk('local')
                        ->directory('ai-importer/tmp-configs')
                        ->required(),
                ])
                ->action(fn (array $data) => $this->importJsonConfig($data)),

            Actions\Action::make('importPreparedCsv')
                ->label('Importer un CSV préparé')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->modalHeading('Importer un CSV déjà préparé')
                ->modalDescription('Charge directement les lignes en staging, sans configuration ni traitement IA. Séparateur « ; », 1ʳᵉ ligne = en-têtes.')
                ->form([
                    Forms\Components\TextInput::make('import_name')
                        ->label('Nom de l\'import')
                        ->placeholder('Optionnel — défaut : nom du fichier')
                        ->maxLength(128),
                    Forms\Components\FileUpload::make('csv_file')
                        ->label('Fichier CSV préparé')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->disk(config('ai-importer.storage.disk', 'local'))
                        ->directory(config('ai-importer.storage.inputs_path', 'ai-importer/inputs'))
                        ->required(),
                ])
                ->action(fn (array $data) => $this->importPreparedCsv($data)),

            Actions\Action::make('runCron')
                ->label('Exécuter CRON')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Lance immédiatement les imports Lunar programmés dont la date est échue (équivalent de la commande planifiée ai-importer:run-scheduled).')
                ->action(function (): void {
                    Artisan::call('ai-importer:run-scheduled');
                    Notification::make()
                        ->success()
                        ->title('CRON exécuté')
                        ->body(trim(Artisan::output()) ?: 'Aucun job programmé dû.')
                        ->send();
                }),
        ];
    }

    /**
     * Importe un JSON Publiko AI Importer via la commande dédiée (réutilise la
     * normalisation + le rapport de compatibilité côté CLI).
     *
     * @param  array<string, mixed>  $data
     */
    public function importJsonConfig(array $data): void
    {
        $path = $data['config_file'] ?? null;
        if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
            Notification::make()->danger()->title('Fichier JSON introuvable')->send();

            return;
        }

        $absolute = Storage::disk('local')->path($path);
        $name = trim((string) ($data['config_name'] ?? ''));

        $options = ['file' => $absolute, '--replace' => true];
        if ($name !== '') {
            $options['--name'] = $name;
        }

        $exit = Artisan::call('ai-importer:import-ps-config', $options);
        Storage::disk('local')->delete($path);

        if ($exit === 0) {
            Notification::make()->success()->title('Configuration importée')->body(trim(Artisan::output()))->send();
        } else {
            Notification::make()->danger()->title('Échec de l\'import')->body(trim(Artisan::output()))->send();
        }
    }

    /**
     * Importe un CSV déjà préparé directement en staging (sans config), puis
     * redirige vers la vue du job créé. Portage de la fonction read-only
     * `ajaxProcessImportPreparedCsv` du module PrestaShop.
     *
     * @param  array<string, mixed>  $data
     */
    public function importPreparedCsv(array $data): void
    {
        $path = $data['csv_file'] ?? null;
        $disk = config('ai-importer.storage.disk', 'local');

        if (! is_string($path) || ! Storage::disk($disk)->exists($path)) {
            Notification::make()->danger()->title('Fichier CSV introuvable')->send();

            return;
        }

        try {
            $job = app(PreparedCsvImporter::class)->import($path, $data['import_name'] ?? null);
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Échec de l\'import CSV')->body($e->getMessage())->send();

            return;
        }

        if ($job->staging_count === 0) {
            Notification::make()->warning()->title('Aucune ligne importée')
                ->body('Le fichier ne contient aucune ligne de données.')->send();
        } else {
            Notification::make()->success()->title('CSV importé en staging')
                ->body($job->staging_count.' ligne(s) prête(s) à l\'import.')->send();
        }

        $this->redirect(ImportJobResource::getUrl('view', ['record' => $job]));
    }

    public function duplicateImporterConfig(int $id): void
    {
        $config = ImporterConfig::find($id);
        if (! $config) {
            return;
        }

        $clone = $config->replicate();
        $clone->name = $config->name.' (copie)';
        $clone->save();

        Notification::make()->success()->title('Configuration dupliquée')->send();
    }

    public function deleteImporterConfig(int $id): void
    {
        ImporterConfig::whereKey($id)->delete();

        Notification::make()->success()->title('Configuration supprimée')->send();
    }

    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Préparer les données')
            ->icon('heroicon-o-play');
    }

    protected function getCreateAnotherFormAction(): Actions\Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uuid'] ??= (string) Str::uuid();
        $data['status'] ??= JobStatus::Pending->value;
        $data['import_status'] ??= ImportStatus::Pending->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        ParseFileToStagingJob::dispatch($this->record->id)
            ->onQueue(config('ai-importer.queues.parse', 'ai-importer-parse'));
    }
}
