# pko/lunar-admin-nav

Réorganisation centralisée du menu Filament admin.

## Rôle

- Redéfinit la hiérarchie complète du menu via `NavigationBuilder` (5 groupes + section Pilotage sans label).
- Ajoute des raccourcis Pilotage (doublons volontaires) : Tableau de bord, Commandes, Expédition, Clients.
- Fusionne Fidélité (4 entrées → 1 hub avec onglets) et Page d'accueil (3 entrées → 1 hub avec onglets).
- Les onglets des hubs sont des `TableWidget` réutilisant `Resource::table()` natif → zéro duplication.

## Installation

Auto-discovery via `extra.laravel.providers`. Enregistré dans `AppServiceProvider::panel()` :

```php
->plugin(\Pko\AdminNav\Filament\AdminNavPlugin::make())
```

## Dépendances

Toutes les packages PKO dont les Resources sont référencées par le Builder (loyalty, storefront-cms, ai-importer, catalog-features, product-documents, shipping-common, store-locator).

## URLs

- `/admin/fidelite` — LoyaltyHub (onglets: Paliers, Cadeaux, Historique, Configuration)
- `/admin/page-accueil` — HomepageHub (onglets: Slides, Tuiles, Offres)

Les URLs originales des Resources fusionnées restent accessibles mais ne sont plus listées au menu.
