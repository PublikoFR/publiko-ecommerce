# pko/lunar-storefront

Frontoffice B2B Lunar : pages produit/collection/recherche, add-to-cart, checkout multi-step. Composants Blade + Livewire prêts à l'emploi.

## Installation

```bash
composer require pko/lunar-storefront
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-storefront-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0
- `livewire/livewire` ^3.0

## Licence

Proprietary — Publiko.
