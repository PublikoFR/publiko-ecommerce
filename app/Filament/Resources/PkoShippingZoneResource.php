<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PkoShippingZoneResource\Pages\PkoEditShippingZone;
use App\Filament\Resources\PkoShippingZoneResource\Pages\PkoListShippingZones;
use App\Filament\Resources\PkoShippingZoneResource\Pages\PkoManageShippingExclusions;
use App\Filament\Resources\PkoShippingZoneResource\Pages\PkoManageShippingRates;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource;
use Pko\ShippingCommon\Filament\Clusters\Shipping;

class PkoShippingZoneResource extends ShippingZoneResource
{
    protected static ?string $cluster = Shipping::class;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListShippingZones::route('/'),
            'edit' => PkoEditShippingZone::route('/{record}/edit'),
            'rates' => PkoManageShippingRates::route('/{record}/rates'),
            'exclusions' => PkoManageShippingExclusions::route('/{record}/exclusions'),
        ];
    }
}
