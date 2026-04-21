<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTaxZoneResource\Pages;

use Lunar\Admin\Filament\Resources\TaxZoneResource\Pages\CreateTaxZone;
use Pko\AdminNav\Filament\Resources\PkoTaxZoneResource;

class PkoCreateTaxZone extends CreateTaxZone
{
    protected static string $resource = PkoTaxZoneResource::class;
}
