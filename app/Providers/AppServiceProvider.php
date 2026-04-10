<?php

declare(strict_types=1);

namespace App\Providers;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::panel(function (Panel $panel): Panel {
            return $panel
                ->path('admin')
                ->brandName('MDE Distribution')
                ->navigationGroups([
                    'Catalogue',
                    'Commandes',
                    'Clients',
                    'Marketing',
                    'Configuration',
                ])
                ->plugin(FilamentShieldPlugin::make());
        })->register();
    }

    public function boot(): void
    {
        //
    }
}
