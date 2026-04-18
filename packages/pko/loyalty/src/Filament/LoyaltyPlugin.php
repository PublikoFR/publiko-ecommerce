<?php

declare(strict_types=1);

namespace Pko\Loyalty\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Pko\Loyalty\Filament\Pages\LoyaltySettings;
use Pko\Loyalty\Filament\Resources\GiftHistoryResource;
use Pko\Loyalty\Filament\Resources\LoyaltyTierResource;
use Pko\Loyalty\Filament\Resources\PointsHistoryResource;

class LoyaltyPlugin implements Plugin
{
    public function getId(): string
    {
        return 'loyalty';
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
