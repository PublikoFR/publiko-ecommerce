<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\SubNavigationPosition;

/**
 * Cluster « Boutique & paiement » (Organisation A).
 * Regroupe en sub-nav on-page (à droite) : Paramètres storefront, Magasins,
 * Canaux, Langues, Devises, Stripe. (Les Taxes restent un cluster Lunar
 * autonome — Filament n'imbrique pas les clusters.)
 */
class PkoShopPaymentCluster extends Cluster
{
    protected static ?string $slug = 'boutique-paiement';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getNavigationLabel(): string
    {
        return __('admin-nav::admin.clusters.shop_payment');
    }

    /**
     * Sub-nav on-page désactivé : navigation via le sous-menu imbriqué de la sidebar.
     * Réactivable via ADMIN_NAV_HIDE_CLUSTER_SUBNAV=false (fonction cluster conservée).
     * N'impacte pas le routing (routes pilotées par l'enregistrement panel des resources).
     *
     * @return array<class-string>
     */
    public static function getClusteredComponents(): array
    {
        return config('admin-nav.hide_cluster_subnav', true)
            ? []
            : parent::getClusteredComponents();
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('admin-nav::admin.clusters.shop_payment');
    }
}
