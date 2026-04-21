<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTaxRateResource\Pages;

use Lunar\Admin\Filament\Resources\TaxRateResource\Pages\EditTaxRate;
use Pko\AdminNav\Filament\Resources\PkoTaxRateResource;

class PkoEditTaxRate extends EditTaxRate
{
    protected static string $resource = PkoTaxRateResource::class;
}
