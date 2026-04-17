<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Mde\Loyalty\Filament\Pages\LoyaltySettings;
use Mde\Loyalty\Filament\Resources\GiftHistoryResource;
use Mde\Loyalty\Filament\Resources\LoyaltyTierResource;
use Mde\Loyalty\Filament\Resources\PointsHistoryResource;

class LoyaltyPlugin implements Plugin
{
    public function getId(): string
    {
        return 'mde-loyalty';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                LoyaltyTierResource::class,
                GiftHistoryResource::class,
                PointsHistoryResource::class,
            ])
            ->pages([
                LoyaltySettings::class,
            ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
