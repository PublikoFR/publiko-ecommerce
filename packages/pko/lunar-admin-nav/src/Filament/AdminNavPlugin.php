<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationBuilder;
use Filament\Pages\SubNavigationPosition;
use Filament\Panel;
use Lunar\Admin\Filament\Clusters\Taxes;
use Lunar\Admin\Filament\Resources\BrandResource;
use Pko\AdminNav\Filament\Clusters\PkoTaxesCluster;
use Pko\AdminNav\Filament\Pages\HomepageHub;
use Pko\AdminNav\Filament\Pages\LoyaltyHub;
use Pko\AdminNav\Navigation\Builder;
use ReflectionProperty;

class AdminNavPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pko-admin-nav';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                LoyaltyHub::class,
                HomepageHub::class,
            ])
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => Builder::build($builder));

        $this->swapTaxesCluster($panel);
        $this->useTopSubNavigationForBrands();
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Sous-navigation des marques (Informations de base, URLs, Produits,
     * Collections, Page marque) en onglets horizontaux au lieu de la colonne
     * de droite Lunar (End) : la colonne amputait la largeur du page-builder
     * de la page « Page marque ». BrandResource n'étant pas subclassée, on
     * écrase sa propriété statique — toutes ses pages lisent cette valeur via
     * Resource::getSubNavigationPosition().
     */
    private function useTopSubNavigationForBrands(): void
    {
        (new ReflectionProperty(BrandResource::class, 'subNavigationPosition'))
            ->setValue(null, SubNavigationPosition::Top);
    }

    /**
     * Swap le Cluster Lunar Taxes avec PkoTaxesCluster qui force
     * SubNavigationPosition::End (sub-nav à droite au lieu de gauche).
     *
     * Les Resources Tax sont swappées côté LunarPanelManager::$resources
     * dans AppServiceProvider::swapLunarResources() (même mécanisme que products).
     * Le swap shipping est géré par Pko\ShippingCommon\Filament\SwapLunarShippingResourcesPlugin.
     */
    private function swapTaxesCluster(Panel $panel): void
    {
        $prop = new ReflectionProperty($panel, 'clusters');
        $clusters = $prop->getValue($panel);

        $idx = array_search(Taxes::class, $clusters, true);
        if ($idx !== false) {
            $clusters[$idx] = PkoTaxesCluster::class;
            $prop->setValue($panel, $clusters);
        }
    }
}
