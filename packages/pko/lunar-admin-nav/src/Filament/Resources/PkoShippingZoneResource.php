<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Lunar\Shipping\Filament\Resources\ShippingZoneResource;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource\Pages\EditShippingZone;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource\Pages\ManageShippingExclusions;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource\Pages\ManageShippingRates;
use Pko\AdminNav\Filament\Resources\PkoShippingZoneResource\Pages\PkoListShippingZones;

class PkoShippingZoneResource extends ShippingZoneResource
{
    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListShippingZones::route('/'),
            'edit' => EditShippingZone::route('/{record}/edit'),
            'rates' => ManageShippingRates::route('/{record}/rates'),
            'exclusions' => ManageShippingExclusions::route('/{record}/exclusions'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
