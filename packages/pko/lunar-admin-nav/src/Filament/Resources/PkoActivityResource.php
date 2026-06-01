<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\ActivityResource;
use Pko\AdminNav\Filament\Clusters\PkoSystemDataCluster;

class PkoActivityResource extends ActivityResource
{
    protected static ?string $slug = 'activity-log';

    protected static ?string $cluster = PkoSystemDataCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoActivityResource\Pages\PkoListActivities::route('/'),
            'view' => PkoActivityResource\Pages\PkoViewActivity::route('/{record}'),
        ];
    }
}
