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

    public static function getClusterBreadcrumb(): ?string
    {
        return __('admin-nav::admin.clusters.catalogue_settings');
    }
}
