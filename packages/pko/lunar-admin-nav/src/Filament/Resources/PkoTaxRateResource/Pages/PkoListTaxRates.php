<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTaxRateResource\Pages;

use Lunar\Admin\Filament\Resources\TaxRateResource\Pages\ListTaxRates;
use Pko\AdminNav\Filament\Resources\PkoTaxRateResource;

class PkoListTaxRates extends ListTaxRates
{
    protected static string $resource = PkoTaxRateResource::class;
}
