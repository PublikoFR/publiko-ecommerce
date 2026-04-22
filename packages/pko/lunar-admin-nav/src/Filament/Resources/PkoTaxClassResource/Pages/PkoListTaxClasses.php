<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTaxClassResource\Pages;

use Lunar\Admin\Filament\Resources\TaxClassResource\Pages\ListTaxClasses;
use Pko\AdminNav\Filament\Resources\PkoTaxClassResource;

class PkoListTaxClasses extends ListTaxClasses
{
    protected static string $resource = PkoTaxClassResource::class;
}
