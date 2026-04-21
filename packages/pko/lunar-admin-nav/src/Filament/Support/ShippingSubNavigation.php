<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Support;

use Filament\Navigation\NavigationItem;
use Lunar\Shipping\Filament\Resources\ShippingExclusionListResource;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource;
use Pko\ShippingChronopost\Filament\Pages\ChronopostConfig;
use Pko\ShippingColissimo\Filament\Pages\ColissimoConfig;
use Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource;

/**
 * Items de sub-navigation (rendus à droite via SubNavigationPosition::End)
 * partagés par toutes les pages du groupe Expédition.
 */
class ShippingSubNavigation
{
    /**
     * @return array<NavigationItem>
     */
    public static function items(): array
    {
        return [
            NavigationItem::make("Méthodes d'expédition")
                ->icon('heroicon-o-truck')
                ->url(fn () => ShippingMethodResource::getUrl())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.resources.shipping-methods.*')),
            NavigationItem::make("Zones d'expédition")
                ->icon('heroicon-o-globe-europe-africa')
                ->url(fn () => ShippingZoneResource::getUrl())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.resources.shipping-zones.*')),
            NavigationItem::make("Listes d'exclusion d'expédition")
                ->icon('heroicon-o-archive-box-x-mark')
                ->url(fn () => ShippingExclusionListResource::getUrl())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.resources.shipping-exclusion-lists.*')),
            NavigationItem::make('Envois transporteurs')
                ->icon('heroicon-o-truck')
                ->url(fn () => CarrierShipmentResource::getUrl())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.resources.carrier-shipments.*')),
            NavigationItem::make('Chronopost')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(fn () => ChronopostConfig::getUrl())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.pages.chronopost-config')),
            NavigationItem::make('Colissimo')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(fn () => ColissimoConfig::getUrl())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.pages.colissimo-config')),
        ];
    }
}
