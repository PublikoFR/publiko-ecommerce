<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Services\PreparedCsvImporter;

/**
 * Liste des imports : une action principale (« Nouvel import »), le reste dans
 * le menu « Plus » (configurations, fichier déjà préparé, planificateur).
 */
class ListImportJobs extends ListRecords
{
    protected static string $resource = ImportJobResource::class;

    public function getTitle(): string
    {
        return 'Imports';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvel import')
                ->icon('heroicon-m-plus'),

            Actions\Action::make('configs')
                ->label('Configurations')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(ImporterConfigResource::getUrl('index')),

            Actions\ActionGroup::make([
                // Un fichier DÉJÀ préparé (CSV transformé ailleurs) part directement
                // en lignes à vérifier, sans configuration ni traitement IA.
                Actions\Action::make('importPreparation')
                    ->label('Importer un fichier déjà préparé')
                    ->icon('heroicon-o-table-cells')
                    ->modalHeading('Importer un fichier déjà préparé')
                    ->modalDescription('CSV dont les colonnes portent déjà les noms des champs produit (séparateur « ; », 1ʳᵉ ligne = en-têtes). Ses lignes arrivent directement à l\'étape de vérification, sans configuration ni IA.')
                    ->modalSubmitActionLabel('Charger les lignes')
                    ->form([
                        Forms\Components\TextInput::make('import_name')
                            ->label('Nom de l\'import')
                            ->placeholder('Par défaut : nom du fichier')
                            ->maxLength(128),
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('Fichier CSV')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->disk(config('ai-importer.storage.disk', 'local'))
                            ->directory(config('ai-importer.storage.inputs_path', 'ai-importer/inputs'))
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->importPreparedCsv($data)),

                Actions\Action::make('runScheduler')
                    ->label('Lancer le planificateur maintenant')
                    ->icon('heroicon-o-bolt')
                    ->requiresConfirmation()
                    ->modalHeading('Lancer le planificateur maintenant')
                    ->modalDescription('Démarre sans attendre les préparations en file d\'attente et les imports programmés arrivés à échéance. Utile si un import semble ne pas démarrer.')
                    ->modalSubmitActionLabel('Lancer')
                    ->action(function (): void {
                        Artisan::call('ai-importer:run-scheduled');
                        Notification::make()->success()->title('Planificateur exécuté')
                            ->body(trim(Artisan::output()) ?: 'Rien à démarrer.')->send();
                    }),
            ])
                ->label('Plus')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Importe un fichier déjà préparé directement en lignes à vérifier (sans
     * config), puis ouvre l'import créé.
     *
     * @param  array<string, mixed>  $data
     */
    public function importPreparedCsv(array $data): void
    {
        $path = $data['csv_file'] ?? null;
        $disk = config('ai-importer.storage.disk', 'local');

        if (! is_string($path) || ! Storage::disk($disk)->exists($path)) {
            Notification::make()->danger()->title('Fichier introuvable')->send();

            return;
        }

        try {
            $job = app(PreparedCsvImporter::class)->import($path, $data['import_name'] ?? null);
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Fichier illisible')->body($e->getMessage())->send();

            return;
        }

        if ($job->staging_count === 0) {
            Notification::make()->warning()->title('Aucune ligne chargée')
                ->body('Le fichier ne contient aucune ligne de données.')->send();
        } else {
            Notification::make()->success()->title('Lignes chargées')
                ->body($job->staging_count.' ligne(s) à vérifier avant import.')->send();
        }

        $this->redirect(ImportJobResource::getUrl('view', ['record' => $job]));
    }
}
