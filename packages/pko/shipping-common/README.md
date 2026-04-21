# pko/lunar-shipping-common

Abstractions communes aux drivers shipping dynamiques (Chronopost, Colissimo, etc.) : interface `Carrier`, gestion tracking, génération étiquettes.

## Installation

```bash
composer require pko/lunar-shipping-common
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-shipping-common-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0
- `filament/filament` ^3.0

## Licence

Proprietary — Publiko.
