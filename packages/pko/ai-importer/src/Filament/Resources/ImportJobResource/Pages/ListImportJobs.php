<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Pko\AiImporter\Filament\Resources\ImportJobResource;

class ListImportJobs extends ListRecords
{
    protected static string $resource = ImportJobResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nouvel import')];
    }
}
