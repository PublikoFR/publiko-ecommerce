<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PkoProductTypeResource\Pages\PkoCreateProductType;
use App\Filament\Resources\PkoProductTypeResource\Pages\PkoEditProductType;
use App\Filament\Resources\PkoProductTypeResource\Pages\PkoListProductTypes;
use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\ProductTypeResource;
use Pko\AdminNav\Filament\Clusters\PkoCatalogueSettingsCluster;

class PkoProductTypeResource extends ProductTypeResource
{
    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = PkoCatalogueSettingsCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getNavigationGroup(): ?string
    {
        return 'Paramètres catalogue';
    }

    public static function getNavigationParentItem(): ?string
    {
        return null;
    }

    public static function getNavigationLabel(): string
    {
        return 'Types de produits';
    }

    /**
     * Override les 3 pages Lunar avec des sous-classes qui déclarent
     * $resource = PkoProductTypeResource. Sans ça, ListProductTypes utilise
     * son $resource hardcodé = ProductTypeResource (Lunar) et génère les
     * URLs edit/create vers une route 'product-types' inexistante après swap.
     * Cf. PkoListProducts pour le même pattern.
     */
    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListProductTypes::route('/'),
            'create' => PkoCreateProductType::route('/create'),
            'edit' => PkoEditProductType::route('/{record}/edit'),
        ];
    }
}
