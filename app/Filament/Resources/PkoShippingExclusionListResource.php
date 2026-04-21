<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PkoShippingExclusionListResource\Pages\PkoEditShippingExclusionList;
use App\Filament\Resources\PkoShippingExclusionListResource\Pages\PkoListShippingExclusionLists;
use Lunar\Shipping\Filament\Resources\ShippingExclusionListResource;
use Pko\ShippingCommon\Filament\Clusters\Shipping;

class PkoShippingExclusionListResource extends ShippingExclusionListResource
{
    protected static ?string $cluster = Shipping::class;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListShippingExclusionLists::route('/'),
            'edit' => PkoEditShippingExclusionList::route('/{record}/edit'),
        ];
    }
}
