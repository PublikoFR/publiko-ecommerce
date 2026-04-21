# pko/lunar-product-documents

Documents téléchargeables avec catégories pour les fiches produit Lunar (fiches techniques, notices, docs commerciaux…).

## Installation

```bash
composer require pko/lunar-product-documents
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-product-documents-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `pko/lunar-media-core` @dev — médiathèque
- `lunarphp/core` ^1.0
- `filament/filament` ^3.0

## Licence

Proprietary — Publiko.
