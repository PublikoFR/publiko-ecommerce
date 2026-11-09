<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource;
use Pko\AiImporter\Filament\Resources\ImportJobResource;

/**
 * Liste des configurations : c'est ici qu'on en crée, importe (JSON),
 * duplique, exporte — et qu'on lance un import avec l'une d'elles.
 */
class ListImporterConfigs extends ListRecords
{
    protected static string $resource = ImporterConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle configuration')
                ->icon('heroicon-m-plus'),

            Actions\Action::make('importJson')
                ->label('Importer (JSON)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Importer une configuration')
                ->modalDescription('Fichier JSON exporté d\'ici ou d\'un ancien module d\'import. Une configuration du même nom est remplacée.')
                ->modalSubmitActionLabel('Importer')
                ->form([
                    Forms\Components\FileUpload::make('config_file')
                        ->label('Fichier JSON')
                        ->acceptedFileTypes(['application/json', 'text/json', 'text/plain'])
                        ->disk('local')
                        ->directory('ai-importer/tmp-configs')
                        ->required(),
                    Forms\Components\TextInput::make('config_name')
                        ->label('Nom de la configuration')
                        ->placeholder('Par défaut : nom du fichier')
                        ->maxLength(128),
                ])
                ->action(fn (array $data) => $this->importJsonConfig($data)),

            Actions\Action::make('imports')
                ->label('Imports')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->color('gray')
                ->url(ImportJobResource::getUrl('index')),
        ];
    }

    /**
     * Importe un JSON de configuration via la commande dédiée (normalisation
     * des anciens formats + rapport de compatibilité).
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

        $name = trim((string) ($data['config_name'] ?? ''));
        $options = ['file' => Storage::disk('local')->path($path), '--replace' => true];
        if ($name !== '') {
            $options['--name'] = $name;
        }

        $exit = Artisan::call('ai-importer:import-ps-config', $options);
        Storage::disk('local')->delete($path);

        $notification = $exit === 0
            ? Notification::make()->success()->title('Configuration importée')
            : Notification::make()->danger()->title('Import impossible');

        $notification->body(trim(Artisan::output()))->send();
    }
}
