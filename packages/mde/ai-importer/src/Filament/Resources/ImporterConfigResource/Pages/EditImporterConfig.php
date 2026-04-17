<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources\ImporterConfigResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Mde\AiImporter\Filament\Resources\ImporterConfigResource;

class EditImporterConfig extends EditRecord
{
    protected static string $resource = ImporterConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
