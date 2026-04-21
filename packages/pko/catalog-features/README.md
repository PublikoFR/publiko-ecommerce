# pko/lunar-catalog-features

Caractéristiques produits structurées filtrables (couleur, taille, matière…) pour Lunar. Tables `pko_feature_families` + `pko_feature_values` + pivot `pko_feature_value_product`. Façade `Features` pour attach/detach/countsForContext.

## Installation

```bash
composer require pko/lunar-catalog-features
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-catalog-features-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0
- `filament/filament` ^3.0

## Licence

Proprietary — Publiko.
