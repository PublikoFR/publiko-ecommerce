<?php

declare(strict_types=1);

namespace Pko\AdminNav\Navigation;

use App\Filament\Pages\StripeConfig;
use App\Filament\Pages\TreeManager;
use App\Filament\Resources\PkoAttributeGroupResource;
use App\Filament\Resources\PkoCollectionGroupResource;
use App\Filament\Resources\PkoProductResource;
use App\Filament\Resources\PkoProductTypeResource;
use BezhanSalleh\FilamentShield\Resources\RoleResource;
use Closure;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Admin\Filament\Resources\CustomerGroupResource;
use Lunar\Admin\Filament\Resources\CustomerResource;
use Lunar\Admin\Filament\Resources\DiscountResource;
use Lunar\Admin\Filament\Resources\OrderResource;
use Pko\AdminNav\Filament\Clusters\PkoTaxesCluster;
use Pko\AdminNav\Filament\Pages\LoyaltyHub;
use Pko\AdminNav\Filament\Resources\PkoActivityResource;
use Pko\AdminNav\Filament\Resources\PkoChannelResource;
use Pko\AdminNav\Filament\Resources\PkoCurrencyResource;
use Pko\AdminNav\Filament\Resources\PkoStaffResource;
use Pko\AdminNav\Filament\Resources\PkoTagResource;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Filament\Resources\LlmConfigResource;
use Pko\CatalogFeatures\Filament\Resources\FeatureFamilyResource;
use Pko\Pennylane\Filament\Pages\PennylaneConfig;
use Pko\Pennylane\Filament\Resources\PennylaneInvoiceResource;
use Pko\ProductDocuments\Filament\Resources\DocumentCategoryResource;
use Pko\ShippingCommon\Filament\Clusters\Shipping;
use Pko\StorefrontCms\Filament\Pages\PkoMediaLibrary;
use Pko\StorefrontCms\Filament\Pages\StorefrontSettings;
use Pko\StorefrontCms\Filament\Resources\HomeOfferResource;
use Pko\StorefrontCms\Filament\Resources\HomeSlideResource;
use Pko\StorefrontCms\Filament\Resources\HomeTileResource;
use Pko\StorefrontCms\Filament\Resources\NewsletterSubscriberResource;
use Pko\StorefrontCms\Filament\Resources\PostResource;

/**
 * Navigation custom du panel admin — layout « MDE » (sur-mesure Rom).
 *
 * 1 raccourci (Tableau de bord) + 5 groupes : Ventes & Clients, Catalogue,
 * Marketing, Boutique, Configuration. Deux sous-menus IMBRIQUÉS animés dans la
 * sidebar (Catégorisation, et Réglages / Paiements & Facturation) via
 * self::nestedMenu() — le rendu (flèche dépliable + animation x-collapse +
 * indentation) est assuré par l'override de vue
 * resources/views/vendor/filament-panels/components/sidebar/item.blade.php.
 *
 * Appelé depuis AdminNavPlugin via ->navigation(fn (NavigationBuilder) => ...).
 */
class Builder
{
    public static function build(NavigationBuilder $builder): NavigationBuilder
    {
        return $builder
            ->items([
                NavigationItem::make('Tableau de bord')
                    ->icon('heroicon-o-home')
                    ->url(fn (): string => Dashboard::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.lunar.pages.dashboard'))
                    ->sort(1),
            ])
            ->groups([
                NavigationGroup::make('Ventes & Clients')->items(self::sales()),
                NavigationGroup::make('Catalogue')->items(self::catalogue()),
                NavigationGroup::make('Marketing')->items(self::marketing()),
                NavigationGroup::make('Boutique')->items(self::boutique()),
                NavigationGroup::make('Configuration')->items(self::configuration()),
            ]);
    }

    /** @return array<NavigationItem> */
    private static function sales(): array
    {
        return array_values(array_filter([
            self::resItem(CustomerResource::class, 'heroicon-o-users', 'Clients')?->sort(1),
            self::resItem(OrderResource::class, 'heroicon-o-shopping-bag', 'Commandes')?->sort(2),
            self::resItem(PennylaneInvoiceResource::class, 'heroicon-o-document-text', 'Factures')?->sort(3),
            self::resItem(Shipping::class, 'heroicon-o-truck', 'Expédition')?->sort(4),
            self::resItem(CustomerGroupResource::class, 'heroicon-o-user-group', 'Groupes de clients')?->sort(5),
        ]));
    }

    /** @return array<NavigationItem> */
    private static function catalogue(): array
    {
        return array_values(array_filter([
            self::resItem(PkoProductResource::class, 'heroicon-o-cube', 'Produits')?->sort(1),
            self::resItem(PkoMediaLibrary::class, 'heroicon-o-photo', 'Médias')?->sort(2),
            self::nestedMenu('Catégorisation', 'heroicon-o-rectangle-stack', [
                self::linkItem(
                    'Catégories',
                    'heroicon-o-folder-open',
                    fn (): string => TreeManager::getUrl().'?tab=categories',
                    fn (): bool => request()->routeIs('filament.lunar.pages.tree-manager') && request()->query('tab') !== 'features',
                ),
                self::resItem(FeatureFamilyResource::class, 'heroicon-o-list-bullet', 'Caractéristiques'),
                self::resItem(BrandResource::class, 'heroicon-o-bookmark-square', 'Marques'),
                self::resItem(PkoProductTypeResource::class, 'heroicon-o-cube-transparent', 'Types de produits'),
                self::resItem(PkoAttributeGroupResource::class, 'heroicon-o-rectangle-group', 'Groupes d\'attributs'),
                self::resItem(PkoCollectionGroupResource::class, 'heroicon-o-folder', 'Groupes de collections'),
                self::resItem(PkoTagResource::class, 'heroicon-o-hashtag', 'Tags'),
                self::resItem(DocumentCategoryResource::class, 'heroicon-o-document-text', 'Catégories de documents'),
            ], sort: 3),
            self::resItem(ImportJobResource::class, 'heroicon-o-arrow-down-on-square-stack', 'Imports')?->sort(4),
        ]));
    }

    /** @return array<NavigationItem> */
    private static function marketing(): array
    {
        return array_values(array_filter([
            self::resItem(DiscountResource::class, 'heroicon-o-receipt-percent', 'Réductions')?->sort(1),
            self::resItem(LoyaltyHub::class, 'heroicon-o-gift', 'Fidélité')?->sort(2),
            self::resItem(NewsletterSubscriberResource::class, 'heroicon-o-envelope', 'Newsletter')?->sort(3),
        ]));
    }

    /** @return array<NavigationItem> */
    private static function boutique(): array
    {
        return array_values(array_filter([
            self::resItem(HomeSlideResource::class, 'heroicon-o-rectangle-group', 'Slider accueil')?->sort(1),
            self::resItem(HomeTileResource::class, 'heroicon-o-squares-2x2', 'Tuiles accueil')?->sort(2),
            self::resItem(HomeOfferResource::class, 'heroicon-o-megaphone', 'Offres du moment')?->sort(3),
            // « Type de contenus » est déplacé en bouton header de la page Contenus
            // (PostResource), volontairement absent du menu.
            self::resItem(PostResource::class, 'heroicon-o-document-duplicate', 'Contenus')?->sort(4),
        ]));
    }

    /** @return array<NavigationItem> */
    private static function configuration(): array
    {
        return array_values(array_filter([
            self::nestedMenu('Réglages', 'heroicon-o-cog-6-tooth', [
                self::resItem(StorefrontSettings::class, 'heroicon-o-adjustments-horizontal', 'Paramètres'),
                self::resItem(PkoChannelResource::class, 'heroicon-o-signal', 'Canaux'),
                self::resItem(PkoActivityResource::class, 'heroicon-o-clock', 'Activités'),
                self::resItem(RoleResource::class, 'heroicon-o-shield-check', 'Rôles'),
                self::resItem(PkoStaffResource::class, 'heroicon-o-user-circle', 'Personnel'),
            ], sort: 1),
            self::nestedMenu('Paiements & Facturation', 'heroicon-o-credit-card', [
                self::resItem(PkoCurrencyResource::class, 'heroicon-o-banknotes', 'Devises'),
                self::resItem(PkoTaxesCluster::class, 'heroicon-o-calculator', 'Taxes'),
                self::resItem(StripeConfig::class, 'heroicon-o-credit-card', 'Stripe'),
                self::resItem(PennylaneConfig::class, 'heroicon-o-building-library', 'Pennylane'),
            ], sort: 2),
            self::resItem(LlmConfigResource::class, 'heroicon-o-sparkles', 'Configurations LLM')?->sort(3),
        ]));
    }

    /**
     * Item de navigation d'une Resource/Page, icône + libellé forcés, sorti de
     * tout groupe/parent natif (le groupement est géré ici). Null si la classe
     * n'existe pas (package optionnel non installé).
     *
     * @param  class-string  $class
     */
    private static function resItem(string $class, string $icon, ?string $label = null): ?NavigationItem
    {
        if (! class_exists($class) || ! method_exists($class, 'getNavigationItems')) {
            return null;
        }

        $item = $class::getNavigationItems()[0] ?? null;
        if (! $item instanceof NavigationItem) {
            return null;
        }

        $item->group(null)->parentItem(null);

        // Préserver le picto natif de la resource (icône ET activeIcon) pour éviter
        // le mismatch inactif/actif. L'icône passée n'est qu'un FALLBACK appliqué
        // seulement si la resource n'en déclare aucune.
        if ($item->getIcon() === null) {
            $item->icon($icon);
        }

        if ($label !== null) {
            $item->label($label);
        }

        return $item;
    }

    /**
     * Item de navigation custom (lien arbitraire — ex. onglet d'une page).
     */
    private static function linkItem(string $label, string $icon, Closure $url, ?Closure $active = null): NavigationItem
    {
        $item = NavigationItem::make($label)
            ->icon($icon)
            ->group(null)
            ->parentItem(null)
            ->url($url);

        if ($active !== null) {
            $item->isActiveWhen($active);
        }

        return $item;
    }

    /**
     * Item parent à SOUS-MENU IMBRIQUÉ animé (dropdown sidebar). Réutilisable :
     * passer le label, l'icône du parent, et la liste d'enfants (NavigationItem
     * issus de self::resItem()/self::linkItem(), les null sont ignorés). Le rendu
     * est géré par l'override de vue sidebar/item.blade.php.
     *
     * @param  array<NavigationItem|null>  $children
     */
    private static function nestedMenu(string $label, string $icon, array $children, int $sort): NavigationItem
    {
        $items = [];
        $i = 0;
        foreach (array_filter($children) as $child) {
            $items[] = $child->sort(++$i);
        }

        $first = $items[0] ?? null;

        return NavigationItem::make($label)
            ->icon($icon)
            ->sort($sort)
            ->url($first !== null ? fn (): string => (string) $first->getUrl() : fn (): string => '#')
            ->childItems($items);
    }
}
