<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTaxZoneResource\Pages;

use Lunar\Admin\Filament\Resources\TaxZoneResource\Pages\ListTaxZones;
use Pko\AdminNav\Filament\Resources\PkoTaxZoneResource;

class PkoListTaxZones extends ListTaxZones
{
    protected static string $resource = PkoTaxZoneResource::class;
}
