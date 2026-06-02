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

    public static function getClusterBreadcrumb(): ?string
    {
        return __('admin-nav::admin.clusters.shop_payment');
    }
}
