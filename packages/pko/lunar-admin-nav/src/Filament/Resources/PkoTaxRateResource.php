<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\TaxRateResource;
use Pko\AdminNav\Filament\Clusters\PkoTaxesCluster;

class PkoTaxRateResource extends TaxRateResource
{
    protected static ?string $slug = 'tax-rates';

    protected static ?string $cluster = PkoTaxesCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoTaxRateResource\Pages\PkoListTaxRates::route('/'),
            'create' => PkoTaxRateResource\Pages\PkoCreateTaxRate::route('/create'),
            'edit' => PkoTaxRateResource\Pages\PkoEditTaxRate::route('/{record}/edit'),
        ];
    }
}
