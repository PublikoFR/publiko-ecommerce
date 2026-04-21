# pko/lunar-customer-auth

Authentification B2B Lunar : inscription pro, vérification SIRET via INSEE API, gate prix (seuls les clients connectés voient les prix).

## Installation

```bash
composer require pko/lunar-customer-auth
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-customer-auth-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0
- `livewire/livewire` ^3.0

## Licence

Proprietary — Publiko.
