<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Pages\StripeConfig;
use App\Filament\Pages\TreeManager;
use App\Filament\Resources\MdeAttributeGroupResource;
use App\Filament\Resources\MdeCollectionGroupResource;
use App\Filament\Resources\MdeProductOptionResource;
use App\Filament\Resources\MdeProductTypeResource;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources\AttributeGroupResource;
use Lunar\Admin\Filament\Resources\CollectionGroupResource;
use Lunar\Admin\Filament\Resources\ProductOptionResource;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Filament\Resources\ProductTypeResource;
use Lunar\Admin\LunarPanelManager;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Shipping\ShippingPlugin;
use Mde\CatalogFeatures\Filament\CatalogFeaturesPlugin;
use Mde\CatalogFeatures\Filament\Extensions\ProductFeaturesExtension;
use Mde\ShippingChronopost\Filament\ChronopostPlugin;
use Mde\ShippingColissimo\Filament\ColissimoPlugin;
use Mde\ShippingCommon\Filament\ShippingCommonPlugin;
use TomatoPHP\FilamentMediaManager\FilamentMediaManagerPlugin;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->swapLunarResources();

        LunarPanel::panel(function (Panel $panel): Panel {
            return $panel
                ->spa(false)
                ->path('admin')
                ->brandName('MDE Distribution')
                ->navigationGroups([
                    'Catalogue',
                    NavigationGroup::make('Paramètres catalogue')->collapsed(),
                    'Commandes',
                    'Clients',
                    'Marketing',
                    'Expédition',
                    'Configuration',
                ])
                ->pages([
                    StripeConfig::class,
                    TreeManager::class,
                ])
                ->plugin(FilamentShieldPlugin::make())
                ->plugin(ShippingPlugin::make())
                ->plugin(ShippingCommonPlugin::make())
                ->plugin(ChronopostPlugin::make())
                ->plugin(ColissimoPlugin::make())
                ->plugin(CatalogFeaturesPlugin::make())
                ->plugin(FilamentMediaManagerPlugin::make()
                    ->allowSubFolders());
        })->register();

        LunarPanel::extensions([
            ProductResource::class => [
                ProductFeaturesExtension::class,
            ],
        ]);
    }

    public function boot(): void
    {
        //
    }

    /**
     * Swap 4 Lunar resources with MDE subclasses that override navigation placement.
     * Must run BEFORE LunarPanel::panel()->register() reads the static $resources array.
     */
    private function swapLunarResources(): void
    {
        $swaps = [
            ProductTypeResource::class => MdeProductTypeResource::class,
            ProductOptionResource::class => MdeProductOptionResource::class,
            AttributeGroupResource::class => MdeAttributeGroupResource::class,
            CollectionGroupResource::class => MdeCollectionGroupResource::class,
        ];

        $prop = (new \ReflectionClass(LunarPanelManager::class))->getProperty('resources');

        /** @var array<int, class-string> $resources */
        $resources = $prop->getValue();

        foreach ($swaps as $original => $replacement) {
            $idx = array_search($original, $resources, true);
            if ($idx !== false) {
                $resources[$idx] = $replacement;
            }
        }

        $prop->setValue(null, $resources);
    }
}
