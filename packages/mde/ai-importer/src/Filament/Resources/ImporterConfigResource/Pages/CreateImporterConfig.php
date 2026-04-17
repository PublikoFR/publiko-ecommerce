<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources\ImporterConfigResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Mde\AiImporter\Filament\Resources\ImporterConfigResource;

class CreateImporterConfig extends CreateRecord
{
    protected static string $resource = ImporterConfigResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ImporterConfigResource::hydrateVisual($data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ImporterConfigResource::dehydrateVisual($data);
    }
}
