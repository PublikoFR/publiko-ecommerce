# pko/lunar-loyalty

Système de fidélité B2B pour Lunar : points, paliers, récompenses, idempotence par commande.

## Installation

```bash
composer require pko/lunar-loyalty
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-loyalty-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0
- `filament/filament` ^3.0

## Licence

Proprietary — Publiko.
