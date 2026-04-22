<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PkoShippingMethodResource\Pages\PkoEditShippingMethod;
use App\Filament\Resources\PkoShippingMethodResource\Pages\PkoListShippingMethod;
use App\Filament\Resources\PkoShippingMethodResource\Pages\PkoManageShippingMethodAvailability;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource;
use Pko\ShippingCommon\Filament\Clusters\Shipping;

class PkoShippingMethodResource extends ShippingMethodResource
{
    protected static ?string $cluster = Shipping::class;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListShippingMethod::route('/'),
            'edit' => PkoEditShippingMethod::route('/{record}/edit'),
            'availability' => PkoManageShippingMethodAvailability::route('/{record}/availability'),
        ];
    }
}
