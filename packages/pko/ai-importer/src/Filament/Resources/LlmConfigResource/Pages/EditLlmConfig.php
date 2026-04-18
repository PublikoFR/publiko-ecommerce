<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\LlmConfigResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Pko\AiImporter\Filament\Resources\LlmConfigResource;

class EditLlmConfig extends EditRecord
{
    protected static string $resource = LlmConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
