<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTaxClassResource\Pages;

use Lunar\Admin\Filament\Resources\TaxClassResource\Pages\EditTaxClass;
use Pko\AdminNav\Filament\Resources\PkoTaxClassResource;

class PkoEditTaxClass extends EditTaxClass
{
    protected static string $resource = PkoTaxClassResource::class;
}
