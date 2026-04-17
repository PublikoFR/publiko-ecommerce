<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources\LlmConfigResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Mde\AiImporter\Filament\Resources\LlmConfigResource;

class CreateLlmConfig extends CreateRecord
{
    protected static string $resource = LlmConfigResource::class;
}
