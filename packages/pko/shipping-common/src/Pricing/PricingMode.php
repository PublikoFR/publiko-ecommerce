<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pricing;

enum PricingMode: string
{
    case GRID = 'grid';
    case LIVE_WITH_FALLBACK = 'live_with_fallback';
    case LIVE_ONLY = 'live_only';

    public function isLive(): bool
    {
        return $this !== self::GRID;
    }

    public function label(): string
    {
        return match ($this) {
            self::GRID => 'Grille statique',
            self::LIVE_WITH_FALLBACK => 'Live API (avec fallback grille)',
            self::LIVE_ONLY => 'Live API strict',
        };
    }
}
