<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\LlmConfigResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Pko\AiImporter\Filament\Resources\LlmConfigResource;

class CreateLlmConfig extends CreateRecord
{
    protected static string $resource = LlmConfigResource::class;
}
