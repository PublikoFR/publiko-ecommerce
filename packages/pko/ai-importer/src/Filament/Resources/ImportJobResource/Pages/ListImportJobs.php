<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Pko\AiImporter\Filament\Resources\ImportJobResource;

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
            // PS exposait « Importer une préparation » (CSV déjà transformé →
            // staging). Dans ce portage, tout import passe par le flux unifié
            // « Préparer un fichier » + config : on y renvoie directement.
            Actions\Action::make('importPreparation')
                ->label('Importer une préparation')
                ->icon('heroicon-o-document-arrow-up')
                ->color('gray')
                ->tooltip('Un fichier déjà préparé s\'importe via « Préparer un fichier » en sélectionnant la configuration correspondante.')
                ->url(ImportJobResource::getUrl('create')),
        ];
    }
}
