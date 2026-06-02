<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\SubNavigationPosition;

/**
 * Cluster « Paramètres catalogue » (Organisation A).
 * Regroupe en sub-nav on-page (à droite) les réglages de structure du catalogue :
 * Types de produits, Options, Groupes d'attributs, Groupes de collections,
 * Catégories de documents, Tags.
 */
class PkoCatalogueSettingsCluster extends Cluster
{
    protected static ?string $slug = 'parametres-catalogue';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getNavigationLabel(): string
    {
        return __('admin-nav::admin.clusters.catalogue_settings');
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
        return __('admin-nav::admin.clusters.catalogue_settings');
    }
}
