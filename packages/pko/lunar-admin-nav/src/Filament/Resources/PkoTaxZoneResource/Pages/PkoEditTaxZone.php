<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources\PkoTaxZoneResource\Pages;

use Lunar\Admin\Filament\Resources\TaxZoneResource\Pages\EditTaxZone;
use Pko\AdminNav\Filament\Resources\PkoTaxZoneResource;

class PkoEditTaxZone extends EditTaxZone
{
    protected static string $resource = PkoTaxZoneResource::class;
}
