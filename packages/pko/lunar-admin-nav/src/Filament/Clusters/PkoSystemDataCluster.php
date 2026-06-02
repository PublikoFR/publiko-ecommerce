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

    public static function getClusterBreadcrumb(): ?string
    {
        return __('admin-nav::admin.clusters.system_data');
    }
}
