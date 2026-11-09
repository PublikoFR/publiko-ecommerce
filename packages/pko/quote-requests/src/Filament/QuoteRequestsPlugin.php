<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Pko\QuoteRequests\Filament\Resources\QuoteRequestResource;

class QuoteRequestsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pko-quote-requests';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            QuoteRequestResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
