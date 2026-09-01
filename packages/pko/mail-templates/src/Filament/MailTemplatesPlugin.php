<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Pko\MailTemplates\Filament\Resources\MailTemplateResource;

class MailTemplatesPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pko-mail-templates';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            MailTemplateResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
