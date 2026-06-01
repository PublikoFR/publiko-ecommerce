<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoCurrencyResource\Pages;

use Lunar\Admin\Filament\Resources\CurrencyResource\Pages\EditCurrency;
use Pko\AdminNav\Filament\Resources\PkoCurrencyResource;

class PkoEditCurrency extends EditCurrency
{
    protected static string $resource = PkoCurrencyResource::class;
}
