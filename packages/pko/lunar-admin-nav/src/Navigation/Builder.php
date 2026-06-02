<?php

declare(strict_types=1);

namespace Pko\AdminNav\Navigation;

use App\Filament\Pages\TreeManager;
use App\Filament\Resources\PkoProductResource;
use BezhanSalleh\FilamentShield\Resources\RoleResource;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Admin\Filament\Resources\CustomerGroupResource;
use Lunar\Admin\Filament\Resources\CustomerResource;
use Lunar\Admin\Filament\Resources\DiscountResource;
use Lunar\Admin\Filament\Resources\OrderResource;
use Pko\AdminNav\Filament\Clusters\PkoCatalogueSettingsCluster;
use Pko\AdminNav\Filament\Clusters\PkoShopPaymentCluster;
use Pko\AdminNav\Filament\Clusters\PkoSystemDataCluster;
use Pko\AdminNav\Filament\Clusters\PkoTaxesCluster;
use Pko\AdminNav\Filament\Pages\HomepageHub;
use Pko\AdminNav\Filament\Pages\LoyaltyHub;
use Pko\Pennylane\Filament\Pages\PennylaneConfig;
use Pko\Pennylane\Filament\Resources\PennylaneInvoiceResource;
use Pko\ShippingCommon\Filament\Clusters\Shipping;
use Pko\StorefrontCms\Filament\Pages\PkoMediaLibrary;
use Pko\StorefrontCms\Filament\Resources\NewsletterSubscriberResource;
use Pko\StorefrontCms\Filament\Resources\PostResource;
use Pko\StorefrontCms\Filament\Resources\PostTypeResource;

/**
 * Construit la navigation complète du panel admin Filament — Organisation A
 * (« Consolidation par clusters »). 5 pôles : Pilotage (raccourcis) + Catalogue,
 * Ventes & Clients, Contenu, Configuration. Les grappes de réglages sont repliées
 * en clusters on-page (sub-nav à droite) pour réduire la sidebar (~37 → ~19).
 *
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
                NavigationGroup::make(__('admin-nav::admin.groups.sales'))
                    ->items(self::sales()),
                NavigationGroup::make(__('admin-nav::admin.groups.content'))
                    ->items(self::content()),
                NavigationGroup::make(__('admin-nav::admin.groups.config'))
                    ->items(self::configuration()),
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
                ->url(fn () => Shipping::getUrl())
                ->isActiveWhen(fn () => request()->routeIs('filament.lunar.expedition.*'))
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
            self::taxonomyItem(sort: 4),
            // Cluster « Paramètres catalogue » : Types, Options, Groupes d'attributs,
            // Groupes de collections, Catégories de documents, Tags (sub-nav on-page).
            ...self::navItems(PkoCatalogueSettingsCluster::class, sort: 5),
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

    /**
     * Groupe « Configuration » (fusion Général + Imports + Boutique + Paiement + Compta).
     * Deux nouveaux clusters + le cluster Taxes Lunar (non imbricable) + Rôles (resource
     * Shield, laissée plate) + Comptabilité (2 entrées Pennylane).
     *
     * @return array<NavigationItem>
     */
    private static function configuration(): array
    {
        return [
            // Cluster « Boutique & paiement » : Paramètres storefront, Magasins,
            // Canaux, Langues, Devises, Stripe (sub-nav on-page).
            ...self::navItems(PkoShopPaymentCluster::class, sort: 1),
            // Cluster « Système & données » : Personnel, Config LLM, Imports,
            // Config d'import, Activités (sub-nav on-page).
            ...self::navItems(PkoSystemDataCluster::class, sort: 2),
            // Cluster Lunar Taxes (Zones / Classes / Taux) — clusters non imbricables
            // dans Filament, donc reste une entrée autonome.
            ...self::navItems(PkoTaxesCluster::class, sort: 3),
            // Rôles : resource Shield hors LunarPanelManager::$resources → non swappable
            // par le mécanisme cluster, laissée en entrée plate.
            ...self::navItems(RoleResource::class, sort: 4),
            // Comptabilité (Pennylane).
            ...self::navItems(PennylaneInvoiceResource::class, sort: 5),
            ...self::navItems(PennylaneConfig::class, sort: 6),
        ];
    }

    /**
     * Entrée unique « Taxonomie » : la page TreeManager gère déjà Catégories +
     * Caractéristiques via onglets (?tab=). Une seule entrée sidebar au lieu de deux.
     */
    private static function taxonomyItem(int $sort): NavigationItem
    {
        return NavigationItem::make(__('admin-nav::admin.catalogue.taxonomy'))
            ->icon('heroicon-o-rectangle-stack')
            ->sort($sort)
            ->url(fn () => TreeManager::getUrl())
            ->isActiveWhen(fn (): bool => request()->routeIs(TreeManager::getNavigationItemActiveRoutePattern()));
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
