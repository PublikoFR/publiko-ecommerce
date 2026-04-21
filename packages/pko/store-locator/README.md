# pko/lunar-store-locator

Carte et annuaire des magasins physiques (Leaflet + OpenStreetMap) pour storefront Lunar.

## Installation

```bash
composer require pko/lunar-store-locator
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-store-locator-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0
- `filament/filament` ^3.0

## Licence

Proprietary — Publiko.
