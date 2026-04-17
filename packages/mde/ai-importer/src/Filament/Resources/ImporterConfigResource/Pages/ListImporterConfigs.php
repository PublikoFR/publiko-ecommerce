<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources\ImporterConfigResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Mde\AiImporter\Filament\Resources\ImporterConfigResource;

class ListImporterConfigs extends ListRecords
{
    protected static string $resource = ImporterConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
