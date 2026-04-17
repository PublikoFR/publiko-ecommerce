<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources\LlmConfigResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Mde\AiImporter\Filament\Resources\LlmConfigResource;

class ListLlmConfigs extends ListRecords
{
    protected static string $resource = LlmConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
