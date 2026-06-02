<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PkoProductOptionResource\Pages\PkoCreateProductOption;
use App\Filament\Resources\PkoProductOptionResource\Pages\PkoEditProductOption;
use App\Filament\Resources\PkoProductOptionResource\Pages\PkoListProductOptions;
use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\ProductOptionResource;
use Pko\AdminNav\Filament\Clusters\PkoCatalogueSettingsCluster;

class PkoProductOptionResource extends ProductOptionResource
{
    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = PkoCatalogueSettingsCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getNavigationGroup(): ?string
    {
        return 'Paramètres catalogue';
    }

    public static function getNavigationLabel(): string
    {
        return 'Options de produits';
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListProductOptions::route('/'),
            'create' => PkoCreateProductOption::route('/create'),
            'edit' => PkoEditProductOption::route('/{record}/edit'),
        ];
    }
}
