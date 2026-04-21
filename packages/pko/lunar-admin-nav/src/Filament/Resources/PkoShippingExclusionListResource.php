<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Lunar\Shipping\Filament\Resources\ShippingExclusionListResource;
use Lunar\Shipping\Filament\Resources\ShippingExclusionListResource\Pages\EditShippingExclusionList;
use Pko\AdminNav\Filament\Resources\PkoShippingExclusionListResource\Pages\PkoListShippingExclusionLists;

class PkoShippingExclusionListResource extends ShippingExclusionListResource
{
    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListShippingExclusionLists::route('/'),
            'edit' => EditShippingExclusionList::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
