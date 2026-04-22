<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTaxClassResource\Pages;

use Lunar\Admin\Filament\Resources\TaxClassResource\Pages\CreateTaxClass;
use Pko\AdminNav\Filament\Resources\PkoTaxClassResource;

class PkoCreateTaxClass extends CreateTaxClass
{
    protected static string $resource = PkoTaxClassResource::class;
}
