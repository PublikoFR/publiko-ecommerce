<?php

declare(strict_types=1);

namespace Pko\Pennylane\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Pko\Pennylane\Filament\Pages\PennylaneConfig;
use Pko\Pennylane\Filament\Resources\PennylaneInvoiceResource;

final class PennylanePlugin implements Plugin
{
    public function getId(): string
    {
        return 'pko-pennylane';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverClusters(
                in: dirname(__DIR__).'/Filament/Clusters',
                for: 'Pko\\Pennylane\\Filament\\Clusters',
            )
            ->pages([
                PennylaneConfig::class,
            ])
            ->resources([
                PennylaneInvoiceResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(self::class);
    }
}
