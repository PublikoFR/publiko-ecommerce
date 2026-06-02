<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\SubNavigationPosition;

/**
 * Cluster « Système & données » (Organisation A).
 * Regroupe en sub-nav on-page (à droite) : Personnel, Config LLM, Imports,
 * Config d'import, Activités. (Rôles reste une entrée plate — resource Shield
 * hors LunarPanelManager::$resources, non swappable par le même mécanisme.)
 */
class PkoSystemDataCluster extends Cluster
{
    protected static ?string $slug = 'systeme-donnees';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getNavigationLabel(): string
    {
        return __('admin-nav::admin.clusters.system_data');
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
        return __('admin-nav::admin.clusters.system_data');
    }
}
