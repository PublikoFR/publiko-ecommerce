# pko/lunar-storefront-cms

CMS unifié multi-post-type (articles, pages, guides…) + médiathèque admin + pages marque avec builder. Réutilise `pko/lunar-page-builder` et `pko/lunar-media-core`.

## Installation

```bash
composer require pko/lunar-storefront-cms
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-storefront-cms-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `pko/lunar-media-core` @dev
- `pko/lunar-page-builder` @dev
- `lunarphp/core` ^1.0
- `filament/filament` ^3.0

## Licence

Proprietary — Publiko.
