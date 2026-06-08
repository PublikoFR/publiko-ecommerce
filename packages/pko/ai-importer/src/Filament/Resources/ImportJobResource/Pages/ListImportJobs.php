<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Services\PreparedCsvImporter;

class ListImportJobs extends ListRecords
{
    protected static string $resource = ImportJobResource::class;

    public function getTitle(): string
    {
        return 'Liste des imports';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Préparer un fichier')
                ->icon('heroicon-o-arrow-up-tray'),

            // « Importer une préparation » : un fichier DÉJÀ préparé (CSV
            // transformé) part directement en staging, sans configuration ni
            // traitement IA. Équivalent du bouton « Importer un CSV préparé »
            // de la page « Préparer un fichier ».
            Actions\Action::make('importPreparation')
                ->label('Importer une préparation')
                ->icon('heroicon-o-document-arrow-up')
                ->color('gray')
                ->modalHeading('Importer une préparation')
                ->modalDescription('Charge directement les lignes en staging, sans configuration ni traitement IA. Séparateur « ; », 1ʳᵉ ligne = en-têtes.')
                ->form([
                    Forms\Components\TextInput::make('import_name')
                        ->label('Nom de l\'import')
                        ->placeholder('Optionnel — défaut : nom du fichier')
                        ->maxLength(128),
                    Forms\Components\FileUpload::make('csv_file')
                        ->label('Fichier préparé')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->disk(config('ai-importer.storage.disk', 'local'))
                        ->directory(config('ai-importer.storage.inputs_path', 'ai-importer/inputs'))
                        ->required(),
                ])
                ->action(fn (array $data) => $this->importPreparedCsv($data)),
        ];
    }

    /**
     * Importe un fichier déjà préparé directement en staging (sans config),
     * puis redirige vers la vue du job créé.
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
            Notification::make()->danger()->title('Échec de l\'import')->body($e->getMessage())->send();

            return;
        }

        if ($job->staging_count === 0) {
            Notification::make()->warning()->title('Aucune ligne importée')
                ->body('Le fichier ne contient aucune ligne de données.')->send();
        } else {
            Notification::make()->success()->title('Préparation importée en staging')
                ->body($job->staging_count.' ligne(s) prête(s) à l\'import.')->send();
        }

        $this->redirect(ImportJobResource::getUrl('view', ['record' => $job]));
    }
}
