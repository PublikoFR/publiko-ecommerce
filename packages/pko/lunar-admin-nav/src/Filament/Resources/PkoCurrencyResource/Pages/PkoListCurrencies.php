<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoCurrencyResource\Pages;

use Lunar\Admin\Filament\Resources\CurrencyResource\Pages\ListCurrencies;
use Pko\AdminNav\Filament\Resources\PkoCurrencyResource;

class PkoListCurrencies extends ListCurrencies
{
    protected static string $resource = PkoCurrencyResource::class;
}
