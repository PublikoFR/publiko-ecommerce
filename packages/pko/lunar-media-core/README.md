# pko/lunar-media-core

Foundation média unifiée pour Lunar : table pivot polymorphique `pko_mediables`, trait `HasMediaAttachments`, composant Filament `MediaPicker`.

## Installation

```bash
composer require pko/lunar-media-core
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-media-core-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0 — catalogue Lunar
- `spatie/laravel-medialibrary` ^11.0 — stockage fichiers
- `filament/filament` ^3.0 — composant form

## Licence

Proprietary — Publiko.
