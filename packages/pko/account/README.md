# pko/lunar-account

Espace client B2B : tableau de bord, commandes, adresses, utilisateurs société.

## Installation

```bash
composer require pko/lunar-account
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-account-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0
- `livewire/livewire` ^3.0

## Licence

Proprietary — Publiko.
