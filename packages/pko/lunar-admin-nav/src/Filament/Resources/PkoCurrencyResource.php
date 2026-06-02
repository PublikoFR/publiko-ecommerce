<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\CurrencyResource;
use Pko\AdminNav\Filament\Clusters\PkoShopPaymentCluster;

class PkoCurrencyResource extends CurrencyResource
{
    protected static ?string $slug = 'currencies';

    protected static ?string $cluster = PkoShopPaymentCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoCurrencyResource\Pages\PkoListCurrencies::route('/'),
            'create' => PkoCurrencyResource\Pages\PkoCreateCurrency::route('/create'),
            'edit' => PkoCurrencyResource\Pages\PkoEditCurrency::route('/{record}/edit'),
        ];
    }
}
