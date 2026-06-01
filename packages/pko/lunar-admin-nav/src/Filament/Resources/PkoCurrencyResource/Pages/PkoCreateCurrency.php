<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoCurrencyResource\Pages;

use Lunar\Admin\Filament\Resources\CurrencyResource\Pages\CreateCurrency;
use Pko\AdminNav\Filament\Resources\PkoCurrencyResource;

class PkoCreateCurrency extends CreateCurrency
{
    protected static string $resource = PkoCurrencyResource::class;
}
