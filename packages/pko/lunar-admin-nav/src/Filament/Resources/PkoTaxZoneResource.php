<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\TaxZoneResource;
use Pko\AdminNav\Filament\Clusters\PkoTaxesCluster;

class PkoTaxZoneResource extends TaxZoneResource
{
    protected static ?string $slug = 'tax-zones';

    protected static ?string $cluster = PkoTaxesCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoTaxZoneResource\Pages\PkoListTaxZones::route('/'),
            'create' => PkoTaxZoneResource\Pages\PkoCreateTaxZone::route('/create'),
            'edit' => PkoTaxZoneResource\Pages\PkoEditTaxZone::route('/{record}/edit'),
        ];
    }
}
