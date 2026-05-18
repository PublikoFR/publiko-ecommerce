<?php

declare(strict_types=1);

namespace Pko\Pennylane\Filament\Clusters;

use Filament\Clusters\Cluster;

class PennylaneCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-euro';

    protected static ?string $slug = 'pennylane';

    protected static ?int $navigationSort = 70;

    public static function getNavigationLabel(): string
    {
        return __('pko-pennylane::admin.cluster.nav');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('pko-pennylane::admin.cluster.nav');
    }
}
