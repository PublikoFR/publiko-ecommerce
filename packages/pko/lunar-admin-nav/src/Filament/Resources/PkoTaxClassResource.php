<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\TaxClassResource;
use Pko\AdminNav\Filament\Clusters\PkoTaxesCluster;

class PkoTaxClassResource extends TaxClassResource
{
    protected static ?string $slug = 'tax-classes';

    protected static ?string $cluster = PkoTaxesCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;
}
