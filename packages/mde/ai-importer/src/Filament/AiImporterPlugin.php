<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Mde\AiImporter\Filament\Resources\ImporterConfigResource;
use Mde\AiImporter\Filament\Resources\ImportJobResource;
use Mde\AiImporter\Filament\Resources\LlmConfigResource;

class AiImporterPlugin implements Plugin
{
    public function getId(): string
    {
        return 'mde-ai-importer';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            ImportJobResource::class,
            ImporterConfigResource::class,
            LlmConfigResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
