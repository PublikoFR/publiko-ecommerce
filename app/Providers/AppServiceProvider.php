<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Extensions\DisableBrokenChartsExtension;
use App\Filament\Pages\StripeConfig;
use App\Filament\Pages\TreeManager;
use App\Filament\Resources\MdeAttributeGroupResource;
use App\Filament\Resources\MdeBrandResource;
use App\Filament\Resources\MdeCollectionGroupResource;
use App\Filament\Resources\MdeCollectionResource;
use App\Filament\Resources\MdeProductOptionResource;
use App\Filament\Resources\MdeProductResource;
use App\Filament\Resources\MdeProductTypeResource;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Pages\Dashboard;
use Lunar\Admin\Filament\Resources\AttributeGroupResource;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Admin\Filament\Resources\CollectionGroupResource;
use Lunar\Admin\Filament\Resources\CollectionResource;
use Lunar\Admin\Filament\Resources\CustomerResource;
use Lunar\Admin\Filament\Resources\ProductOptionResource;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Filament\Resources\ProductTypeResource;
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
use Mde\StorefrontCms\Filament\MediaManagerShimPlugin;
use Mde\StorefrontCms\Filament\Pages\StorefrontSettings;
use Mde\StorefrontCms\Filament\StorefrontCmsPlugin;
use Mde\StoreLocator\Filament\StoreLocatorPlugin;

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
                ->viteTheme('resources/css/filament/admin/theme.css')
                ->navigationGroups([
                    'Catalogue',
                    NavigationGroup::make('Paramètres catalogue')->collapsed(),
                    'Storefront',
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
                    StorefrontSettings::class,
                ])
                ->plugin(FilamentShieldPlugin::make())
                ->plugin(ShippingPlugin::make())
                ->plugin(ShippingCommonPlugin::make())
                ->plugin(ChronopostPlugin::make())
                ->plugin(ColissimoPlugin::make())
                ->plugin(CatalogFeaturesPlugin::make())
                ->plugin(AiImporterPlugin::make())
                ->plugin(LoyaltyPlugin::make())
                ->plugin(StorefrontCmsPlugin::make())
                ->plugin(StoreLocatorPlugin::make())
                ->plugin(MediaManagerShimPlugin::make());
        })->register();

        LunarPanel::extensions([
            // Register under MDE subclass (post-swap, hooks dispatch with static::class).
            MdeProductResource::class => [
                ProductFeaturesExtension::class,
            ],
            CustomerResource::class => [
                CustomerLoyaltyExtension::class,
            ],
            Dashboard::class => [
                DisableBrokenChartsExtension::class,
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
            ProductResource::class => MdeProductResource::class,
            CollectionResource::class => MdeCollectionResource::class,
            BrandResource::class => MdeBrandResource::class,
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
