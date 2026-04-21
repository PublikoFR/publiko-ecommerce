# pko/lunar-page-builder

Mini page-builder JSON universel : sections + colonnes (1/2/3) + blocs text/image/code. AI-friendly via JSON schema canonique. Sanitization HTML côté serveur via HTMLPurifier.

## Installation

```bash
composer require pko/lunar-page-builder
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-page-builder-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `livewire/livewire` ^3.0
- `filament/filament` ^3.0
- `mews/purifier` ^3.4 — sanitization HTML

## Licence

Proprietary — Publiko.
