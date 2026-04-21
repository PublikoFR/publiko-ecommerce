# pko/lunar-purchase-lists

Listes d'achat récurrentes B2B : créer, modifier et réutiliser des paniers types.

## Installation

```bash
composer require pko/lunar-purchase-lists
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-purchase-lists-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0
- `livewire/livewire` ^3.0

## Licence

Proprietary — Publiko.
