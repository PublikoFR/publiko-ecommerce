<?php

declare(strict_types=1);

namespace Mde\CatalogFeatures\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource;

class CatalogFeaturesPlugin implements Plugin
{
    public function getId(): string
    {
        return 'mde-catalog-features';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            FeatureFamilyResource::class,
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
