# pko/lunar-product-videos

Vidéos produit multi-provider (YouTube, Vimeo, Dailymotion, MP4) pour Lunar. Détection automatique du provider + génération oEmbed thumbnails.

## Installation

```bash
composer require pko/lunar-product-videos
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-product-videos-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `lunarphp/core` ^1.0
- `filament/filament` ^3.0

## Licence

Proprietary — Publiko.
