<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Clusters;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Clusters\Taxes;
use Pko\AdminNav\Filament\Concerns\RedirectsToFirstClusteredComponent;

class PkoTaxesCluster extends Taxes
{
    use RedirectsToFirstClusteredComponent;

    protected static ?string $slug = 'taxes';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;
}
