<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * Second navigation entry that groups per-carrier configuration pages
 * (Chronopost, Colissimo, DPD, UPS…). Positioned right below "Expédition"
 * in the sidebar.
 *
 * Rationale for splitting Shipping and Carriers :
 *   - Shipping groups operational / shipping-ops data (methods, zones,
 *     exclusions, shipments sent)
 *   - Carriers groups carrier integration credentials + tariff grids
 */
class Carriers extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Transporteurs';

    protected static ?string $title = 'Transporteurs';

    protected static ?string $slug = 'transporteurs';

    protected static ?int $navigationSort = 56;
}
