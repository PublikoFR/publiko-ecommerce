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
use Lunar\Admin\Filament\Resources\CustomerResource;
use Lunar\Admin\Filament\Resources\ProductOptionResource;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Filament\Resources\ProductTypeResource;
use Lunar\Admin\Filament\Widgets\Dashboard\Customers\NewVsReturningCustomersChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\AverageOrderValueChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\OrdersSalesChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\OrderTotalsChart;
use Lunar\Admin\LunarPanelManager;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Shipping\ShippingPlugin;
use Mde\AiImporter\Filament\AiImporterPlugin;
use Mde\CatalogFeatures\Filament\CatalogFeaturesPlugin;
use Mde\CatalogFeatures\Filament\Extensions\ProductFeaturesExtension;
use Mde\Loyalty\Filament\Extensions\CustomerLoyaltyExtension;
use Mde\Loyalty\Filament\LoyaltyPlugin;
use Mde\ShippingChronopost\Filament\ChronopostPlugin;
use Mde\ShippingColissimo\Filament\ColissimoPlugin;
use Mde\ShippingCommon\Filament\ShippingCommonPlugin;
use TomatoPHP\FilamentMediaManager\FilamentMediaManagerPlugin;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->swapLunarResources();
        $this->disableBrokenLunarWidgets();

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
                    'Imports',
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
                ->plugin(AiImporterPlugin::make())
                ->plugin(LoyaltyPlugin::make())
                ->plugin(FilamentMediaManagerPlugin::make()
                    ->allowSubFolders());
        })->register();

        LunarPanel::extensions([
            ProductResource::class => [
                ProductFeaturesExtension::class,
            ],
            CustomerResource::class => [
                CustomerLoyaltyExtension::class,
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
    /**
     * Désactive les widgets Lunar qui cassent sur Livewire polling (RootTagMissing).
     * Cause : ApexChartWidget perd le panel context sur /livewire/update → render() vide.
     */
    private function disableBrokenLunarWidgets(): void
    {
        $disabled = [
            OrdersSalesChart::class,
            OrderTotalsChart::class,
            AverageOrderValueChart::class,
            NewVsReturningCustomersChart::class,
        ];

        $prop = (new \ReflectionClass(LunarPanelManager::class))->getProperty('widgets');
        /** @var array<int, class-string> $widgets */
        $widgets = $prop->getValue();
        $prop->setValue(null, array_values(array_diff($widgets, $disabled)));
    }

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
