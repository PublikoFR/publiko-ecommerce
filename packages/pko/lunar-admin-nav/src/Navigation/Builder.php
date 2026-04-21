<?php

declare(strict_types=1);

namespace Pko\AdminNav\Navigation;

use App\Filament\Pages\StripeConfig;
use App\Filament\Pages\TreeManager;
use App\Filament\Resources\PkoAttributeGroupResource;
use App\Filament\Resources\PkoCollectionGroupResource;
use App\Filament\Resources\PkoProductOptionResource;
use App\Filament\Resources\PkoProductResource;
use App\Filament\Resources\PkoProductTypeResource;
use BezhanSalleh\FilamentShield\Resources\RoleResource;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Lunar\Admin\Filament\Resources\ActivityResource;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Admin\Filament\Resources\ChannelResource;
use Lunar\Admin\Filament\Resources\CurrencyResource;
use Lunar\Admin\Filament\Resources\CustomerGroupResource;
use Lunar\Admin\Filament\Resources\CustomerResource;
use Lunar\Admin\Filament\Resources\DiscountResource;
use Lunar\Admin\Filament\Resources\LanguageResource;
use Lunar\Admin\Filament\Resources\OrderResource;
use Lunar\Admin\Filament\Resources\StaffResource;
use Lunar\Admin\Filament\Resources\TagResource;
use Lunar\Admin\Filament\Resources\TaxClassResource;
use Lunar\Admin\Filament\Resources\TaxRateResource;
use Lunar\Admin\Filament\Resources\TaxZoneResource;
use Lunar\Shipping\Filament\Resources\ShippingExclusionListResource;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource;
use Pko\AdminNav\Filament\Pages\HomepageHub;
use Pko\AdminNav\Filament\Pages\LoyaltyHub;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Filament\Resources\LlmConfigResource;
use Pko\ProductDocuments\Filament\Resources\DocumentCategoryResource;
use Pko\ShippingChronopost\Filament\Pages\ChronopostConfig;
use Pko\ShippingColissimo\Filament\Pages\ColissimoConfig;
use Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource;
use Pko\StorefrontCms\Filament\Pages\PkoMediaLibrary;
use Pko\StorefrontCms\Filament\Pages\StorefrontSettings;
use Pko\StorefrontCms\Filament\Resources\NewsletterSubscriberResource;
use Pko\StorefrontCms\Filament\Resources\PostResource;
use Pko\StorefrontCms\Filament\Resources\PostTypeResource;
use Pko\StoreLocator\Filament\Resources\StoreResource;

/**
 * Construit la navigation complète du panel admin Filament.
 * Appelé depuis AdminNavPlugin via ->navigation(fn (NavigationBuilder) => ...).
 */
class Builder
{
    public static function build(NavigationBuilder $builder): NavigationBuilder
    {
        return $builder
            ->items(self::pilotage())
            ->groups([
                NavigationGroup::make(__('admin-nav::admin.groups.catalogue'))
                    ->items(self::catalogue()),
                NavigationGroup::make(__('admin-nav::admin.groups.catalogue_settings'))
                    ->collapsed()
                    ->items(self::catalogueSettings()),
                NavigationGroup::make(__('admin-nav::admin.groups.sales'))
                    ->items(self::sales()),
                NavigationGroup::make(__('admin-nav::admin.groups.content'))
                    ->items(self::content()),
                NavigationGroup::make(__('admin-nav::admin.groups.config_general'))
                    ->collapsed()
                    ->items(self::configGeneral()),
                NavigationGroup::make(__('admin-nav::admin.groups.config_imports'))
                    ->collapsed()
                    ->items(self::configImports()),
                NavigationGroup::make(__('admin-nav::admin.groups.config_shop'))
                    ->collapsed()
                    ->items(self::configShop()),
                NavigationGroup::make(__('admin-nav::admin.groups.config_payment'))
                    ->collapsed()
                    ->items(self::configPayment()),
            ]);
    }

    /**
     * Raccourcis Pilotage (sans label de groupe — rendus en tête de sidebar).
     *
     * @return array<NavigationItem>
     */
    private static function pilotage(): array
    {
        return [
            NavigationItem::make(__('admin-nav::admin.shortcuts.dashboard'))
                ->icon('heroicon-o-chart-bar')
                ->url(fn () => Dashboard::getUrl())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.pages.dashboard'))
                ->sort(1),
            NavigationItem::make(__('admin-nav::admin.shortcuts.orders'))
                ->icon('heroicon-o-inbox')
                ->url(fn () => OrderResource::getUrl())
                ->badge(fn () => OrderResource::getNavigationBadge())
                ->badgeTooltip(fn () => OrderResource::getNavigationBadgeTooltip())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.resources.orders.*'))
                ->sort(2),
            NavigationItem::make(__('admin-nav::admin.shortcuts.shipping'))
                ->icon('heroicon-o-truck')
                ->url(fn () => ShippingMethodResource::getUrl())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.resources.shipping-methods.*')
                    || request()->routeIs('filament.lunar.resources.shipping-zones.*')
                    || request()->routeIs('filament.lunar.resources.shipping-exclusion-lists.*')
                    || request()->routeIs('filament.lunar.resources.carrier-shipments.*'))
                ->sort(3),
            NavigationItem::make(__('admin-nav::admin.shortcuts.customers'))
                ->icon('heroicon-o-users')
                ->url(fn () => CustomerResource::getUrl())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.resources.customers.*'))
                ->sort(4),
        ];
    }

    /** @return array<NavigationItem> */
    private static function catalogue(): array
    {
        return [
            ...self::navItems(PkoProductResource::class, sort: 1),
            ...self::navItems(PkoMediaLibrary::class, sort: 2),
            ...self::navItems(BrandResource::class, sort: 3),
            // TreeManager émet 2 NavigationItems : Catégories + Caractéristiques
            ...self::navItems(TreeManager::class, sort: 4),
        ];
    }

    /** @return array<NavigationItem> */
    private static function catalogueSettings(): array
    {
        return [
            ...self::navItems(PkoProductTypeResource::class, sort: 1),
            ...self::navItems(PkoProductOptionResource::class, sort: 2),
            ...self::navItems(PkoAttributeGroupResource::class, sort: 3),
            ...self::navItems(PkoCollectionGroupResource::class, sort: 4),
            ...self::navItems(DocumentCategoryResource::class, sort: 5),
            ...self::navItems(TagResource::class, sort: 6),
        ];
    }

    /** @return array<NavigationItem> */
    private static function sales(): array
    {
        return [
            ...self::navItems(CustomerGroupResource::class, sort: 1),
            ...self::navItems(DiscountResource::class, sort: 2),
            ...self::navItems(NewsletterSubscriberResource::class, sort: 3),
            ...self::navItems(LoyaltyHub::class, sort: 10),
        ];
    }

    /** @return array<NavigationItem> */
    private static function content(): array
    {
        return [
            ...self::navItems(HomepageHub::class, sort: 1),
            ...self::navItems(PostResource::class, sort: 2),
            ...self::navItems(PostTypeResource::class, sort: 3),
        ];
    }

    /** @return array<NavigationItem> */
    private static function configGeneral(): array
    {
        return [
            ...self::navItems(StaffResource::class, sort: 1),
            ...self::navItems(RoleResource::class, sort: 2),
            ...self::navItems(LlmConfigResource::class, sort: 3),
        ];
    }

    /** @return array<NavigationItem> */
    private static function configImports(): array
    {
        return [
            ...self::navItems(ImportJobResource::class, sort: 1),
            ...self::navItems(ImporterConfigResource::class, sort: 2),
            ...self::navItems(ActivityResource::class, sort: 3),
        ];
    }

    /** @return array<NavigationItem> */
    private static function configShop(): array
    {
        return [
            ...self::navItems(StorefrontSettings::class, sort: 1),
            ...self::navItems(StoreResource::class, sort: 2),
            ...self::navItems(ChannelResource::class, sort: 3),
            ...self::navItems(LanguageResource::class, sort: 4),
        ];
    }

    /** @return array<NavigationItem> */
    private static function configPayment(): array
    {
        return [
            ...self::navItems(CurrencyResource::class, sort: 1),
            ...self::navItems(TaxZoneResource::class, sort: 2),
            ...self::navItems(TaxClassResource::class, sort: 3),
            ...self::navItems(TaxRateResource::class, sort: 4),
            ...self::navItems(StripeConfig::class, sort: 5),
            ...self::navItems(ShippingMethodResource::class, sort: 10),
            ...self::navItems(ShippingZoneResource::class, sort: 11),
            ...self::navItems(ShippingExclusionListResource::class, sort: 12),
            ...self::navItems(CarrierShipmentResource::class, sort: 13),
            ...self::navItems(ChronopostConfig::class, sort: 20),
            ...self::navItems(ColissimoConfig::class, sort: 21),
        ];
    }

    /**
     * Récupère les NavigationItems d'une Resource ou Page en forçant le sort custom,
     * en enlevant le group natif (on gère le groupement ici) et en ignorant si la classe
     * n'existe pas (package optionnel pas installé).
     *
     * @param  class-string  $class
     * @return array<NavigationItem>
     */
    private static function navItems(string $class, int $sort): array
    {
        if (! class_exists($class)) {
            return [];
        }

        if (! method_exists($class, 'getNavigationItems')) {
            return [];
        }

        $items = $class::getNavigationItems();

        foreach ($items as $item) {
            $item->sort($sort)->group(null)->parentItem(null);
        }

        return $items;
    }
}
