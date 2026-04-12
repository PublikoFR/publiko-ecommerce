<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Pages\StripeConfig;
use App\Filament\Pages\TreeManager;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Shipping\ShippingPlugin;
use Mde\CatalogFeatures\Filament\CatalogFeaturesPlugin;
use Mde\CatalogFeatures\Filament\Extensions\ProductFeaturesExtension;
use Mde\ShippingChronopost\Filament\ChronopostPlugin;
use Mde\ShippingColissimo\Filament\ColissimoPlugin;
use Mde\ShippingCommon\Filament\ShippingCommonPlugin;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::panel(function (Panel $panel): Panel {
            return $panel
                ->spa(false)
                ->path('admin')
                ->brandName('MDE Distribution')
                ->navigationGroups([
                    'Catalogue',
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
                ->plugin(CatalogFeaturesPlugin::make());
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
}
