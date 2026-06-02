<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\TagResource;
use Pko\AdminNav\Filament\Clusters\PkoCatalogueSettingsCluster;

class PkoTagResource extends TagResource
{
    protected static ?string $slug = 'tags';

    protected static ?string $cluster = PkoCatalogueSettingsCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoTagResource\Pages\PkoListTags::route('/'),
            'create' => PkoTagResource\Pages\PkoCreateTag::route('/create'),
            'edit' => PkoTagResource\Pages\PkoEditTag::route('/{record}/edit'),
        ];
    }
}
