# pko/lunar-quick-order

Commande rapide B2B : saisie SKU + quantité en masse, upload CSV, recherche live.

## Installation

```bash
composer require pko/lunar-quick-order
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-quick-order-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0
- `livewire/livewire` ^3.0

## Licence

Proprietary — Publiko.
