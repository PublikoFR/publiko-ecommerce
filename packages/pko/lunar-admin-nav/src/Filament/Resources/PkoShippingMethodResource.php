<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Lunar\Shipping\Filament\Resources\ShippingMethodResource;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource\Pages\EditShippingMethod;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource\Pages\ManageShippingMethodAvailability;
use Pko\AdminNav\Filament\Resources\PkoShippingMethodResource\Pages\PkoListShippingMethod;

class PkoShippingMethodResource extends ShippingMethodResource
{
    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListShippingMethod::route('/'),
            'edit' => EditShippingMethod::route('/{record}/edit'),
            'availability' => ManageShippingMethodAvailability::route('/{record}/availability'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
