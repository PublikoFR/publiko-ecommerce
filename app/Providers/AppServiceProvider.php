<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Extensions\DisableBrokenChartsExtension;
use App\Filament\Extensions\HideLunarMediaExtension;
use App\Filament\Pages\StripeConfig;
use App\Filament\Pages\TreeManager;
use App\Filament\Resources\PkoAttributeGroupResource;
use App\Filament\Resources\PkoCollectionGroupResource;
use App\Filament\Resources\PkoProductOptionResource;
use App\Filament\Resources\PkoProductResource;
use App\Filament\Resources\PkoProductTypeResource;
use App\Generators\PkoProductUrlGenerator;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Illuminate\Support\Facades\Blade;
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
use Lunar\Models\ProductVariant;
use Lunar\Shipping\ShippingPlugin;
use Pko\AiImporter\Filament\AiImporterPlugin;
use Pko\CatalogFeatures\Filament\CatalogFeaturesPlugin;
use Pko\CatalogFeatures\Filament\Extensions\ProductFeaturesExtension;
use Pko\Loyalty\Filament\Extensions\CustomerLoyaltyExtension;
use Pko\Loyalty\Filament\LoyaltyPlugin;
use Pko\ProductDocuments\ProductDocumentsPlugin;
use Pko\ShippingChronopost\Filament\ChronopostPlugin;
use Pko\ShippingColissimo\Filament\ColissimoPlugin;
use Pko\ShippingCommon\Filament\ShippingCommonPlugin;
use Pko\StorefrontCms\Filament\MediaManagerShimPlugin;
use Pko\StorefrontCms\Filament\Pages\StorefrontSettings;
use Pko\StorefrontCms\Filament\StorefrontCmsPlugin;
use Pko\StoreLocator\Filament\StoreLocatorPlugin;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->swapLunarResources();

        LunarPanel::panel(function (Panel $panel): Panel {
            return $panel
                ->spa(false)
                ->path('admin')
                ->brandName(brand_name())
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
                ->plugin(ProductDocumentsPlugin::make())
                ->plugin(AiImporterPlugin::make())
                ->plugin(LoyaltyPlugin::make())
                ->plugin(StorefrontCmsPlugin::make())
                ->plugin(StoreLocatorPlugin::make())
                ->plugin(MediaManagerShimPlugin::make());
        })->register();

        LunarPanel::extensions([
            PkoProductResource::class => [
                ProductFeaturesExtension::class,
                HideLunarMediaExtension::class,
            ],
            CollectionResource::class => [
                HideLunarMediaExtension::class,
            ],
            BrandResource::class => [
                HideLunarMediaExtension::class,
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
        // Regenerate product URL slug when variants change — MPN is only
        // available after variant creation so Lunar's native post-create hook
        // runs too early. See App\Generators\PkoProductUrlGenerator::regenerate.
        ProductVariant::saved(function (ProductVariant $variant): void {
            if ($variant->product) {
                app(PkoProductUrlGenerator::class)->regenerate($variant->product);
            }
        });

        Blade::anonymousComponentPath(
            resource_path('views/filament/resources/pko-product/partials'),
            'pko-product'
        );
    }

    /**
     * Swap 4 Lunar resources with our subclasses that override navigation placement.
     * Must run BEFORE LunarPanel::panel()->register() reads the static $resources array.
     */
    private function swapLunarResources(): void
    {
        $swaps = [
            ProductResource::class => PkoProductResource::class,
            ProductTypeResource::class => PkoProductTypeResource::class,
            ProductOptionResource::class => PkoProductOptionResource::class,
            AttributeGroupResource::class => PkoAttributeGroupResource::class,
            CollectionGroupResource::class => PkoCollectionGroupResource::class,
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
