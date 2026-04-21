<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTaxRateResource\Pages;

use Lunar\Admin\Filament\Resources\TaxRateResource\Pages\CreateTaxRate;
use Pko\AdminNav\Filament\Resources\PkoTaxRateResource;

class PkoCreateTaxRate extends CreateTaxRate
{
    protected static string $resource = PkoTaxRateResource::class;
}
