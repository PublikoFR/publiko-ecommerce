<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Clusters;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Clusters\Taxes;

class PkoTaxesCluster extends Taxes
{
    protected static ?string $slug = 'taxes';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;
}
