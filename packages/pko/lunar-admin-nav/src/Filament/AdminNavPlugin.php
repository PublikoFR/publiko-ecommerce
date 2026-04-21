<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Lunar\Shipping\Filament\Resources\ShippingExclusionListResource;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource;
use Pko\AdminNav\Filament\Pages\HomepageHub;
use Pko\AdminNav\Filament\Pages\LoyaltyHub;
use Pko\AdminNav\Filament\Resources\PkoShippingExclusionListResource;
use Pko\AdminNav\Filament\Resources\PkoShippingMethodResource;
use Pko\AdminNav\Filament\Resources\PkoShippingZoneResource;
use Pko\AdminNav\Navigation\Builder;
use ReflectionProperty;

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

        $this->swapShippingResources($panel);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Swap les 3 Resources Lunar shipping enregistrées par ShippingPlugin
     * avec les sous-classes Pko* qui déclarent $subNavigationPosition = End
     * et getSubNavigation() pour rendre la sub-nav on-page.
     *
     * Doit être appelé APRÈS ShippingPlugin::register() — garanti car
     * AdminNavPlugin est enregistré en dernier dans AppServiceProvider.
     */
    private function swapShippingResources(Panel $panel): void
    {
        $swaps = [
            ShippingMethodResource::class => PkoShippingMethodResource::class,
            ShippingZoneResource::class => PkoShippingZoneResource::class,
            ShippingExclusionListResource::class => PkoShippingExclusionListResource::class,
        ];

        $prop = new ReflectionProperty($panel, 'resources');
        $resources = $prop->getValue($panel);

        foreach ($swaps as $original => $replacement) {
            $idx = array_search($original, $resources, true);
            if ($idx !== false) {
                $resources[$idx] = $replacement;
            }
        }

        $prop->setValue($panel, $resources);
    }
}
