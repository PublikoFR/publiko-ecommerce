<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament;

use App\Filament\Resources\PkoShippingExclusionListResource;
use App\Filament\Resources\PkoShippingMethodResource;
use App\Filament\Resources\PkoShippingZoneResource;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Lunar\Shipping\Filament\Resources\ShippingExclusionListResource;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource;
use ReflectionClass;

/**
 * Replaces Lunar Shipping resources (ShippingMethodResource, ShippingZoneResource,
 * ShippingExclusionListResource) with our Pko-prefixed subclasses that declare
 * $cluster = Shipping::class, so the 3 Lunar resources appear inside the
 * "Expédition" cluster instead of a standalone navigation group.
 *
 * Must be chained AFTER Lunar\Shipping\ShippingPlugin::make() so the resources
 * are already registered when we rewrite them.
 *
 * Subclasses must be registered as actual Filament resources separately via
 * the main panel (they can't simply replace vendor classes at array level
 * because Filament resolves them as class names).
 */
class SwapLunarShippingResourcesPlugin implements Plugin
{
    /**
     * Map of vendor resource class → app subclass that adds $cluster.
     */
    protected const SWAPS = [
        ShippingMethodResource::class => PkoShippingMethodResource::class,
        ShippingZoneResource::class => PkoShippingZoneResource::class,
        ShippingExclusionListResource::class => PkoShippingExclusionListResource::class,
    ];

    public function getId(): string
    {
        return 'pko-shipping-swap-lunar-resources';
    }

    public function register(Panel $panel): void
    {
        $reflection = new ReflectionClass($panel);

        $resourcesProp = $reflection->getProperty('resources');
        $clusteredProp = $reflection->getProperty('clusteredComponents');

        /** @var array<int|string, class-string> $resources */
        $resources = $resourcesProp->getValue($panel);
        /** @var array<class-string, array<int, class-string>> $clusteredComponents */
        $clusteredComponents = $clusteredProp->getValue($panel);

        foreach (self::SWAPS as $original => $replacement) {
            $idx = array_search($original, $resources, true);
            if ($idx !== false) {
                $resources[$idx] = $replacement;
            }

            // Mirror what Panel::registerToCluster() would have done if the
            // replacement had been registered via ->resources([...]).
            $cluster = $replacement::getCluster();
            if (! blank($cluster)) {
                $clusteredComponents[$cluster][] = $replacement;
            }
        }

        $resourcesProp->setValue($panel, $resources);
        $clusteredProp->setValue($panel, $clusteredComponents);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
