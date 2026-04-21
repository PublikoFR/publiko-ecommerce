<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * Single navigation entry that groups all shipping admin pages under one
 * tabbed layout in Filament. Contains :
 *   - Lunar Shipping resources (subclassed in app/ to set the $cluster)
 *   - CarrierShipmentResource (ours)
 *   - Per-carrier config pages (ChronopostConfig, ColissimoConfig, …)
 */
class Shipping extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Expédition';

    protected static ?string $title = 'Expédition';

    protected static ?string $slug = 'expedition';

    protected static ?int $navigationSort = 55;
}
