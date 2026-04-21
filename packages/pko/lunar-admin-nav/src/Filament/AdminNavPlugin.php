<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Pko\AdminNav\Filament\Pages\HomepageHub;
use Pko\AdminNav\Filament\Pages\LoyaltyHub;
use Pko\AdminNav\Filament\Support\ShippingSubNavigation;
use Pko\AdminNav\Filament\Support\SideNavRegistry;
use Pko\AdminNav\Navigation\Builder;

class AdminNavPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pko-admin-nav';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                LoyaltyHub::class,
                HomepageHub::class,
            ])
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => Builder::build($builder));
    }

    public function boot(Panel $panel): void
    {
        $this->registerSideNavs();

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => view('admin-nav::side-nav')->render(),
        );
    }

    public static function make(): static
    {
        return app(static::class);
    }

    private function registerSideNavs(): void
    {
        SideNavRegistry::register(
            key: 'expedition',
            matchRoutes: [
                'filament.lunar.resources.shipping-methods.*',
                'filament.lunar.resources.shipping-zones.*',
                'filament.lunar.resources.shipping-exclusion-lists.*',
                'filament.lunar.resources.carrier-shipments.*',
                'filament.lunar.pages.chronopost-config',
                'filament.lunar.pages.colissimo-config',
            ],
            items: fn () => ShippingSubNavigation::items(),
            heading: 'Expédition',
        );
    }
}
