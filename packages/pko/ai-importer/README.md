# pko/lunar-ai-importer

Pipeline d'import produits Lunar via LLM : Excel multi-feuilles, actions chaînées, staging.

## Installation

```bash
composer require pko/lunar-ai-importer
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-ai-importer-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `pko/lunar-ai-core` @dev
- `pko/lunar-catalog-features` @dev
- `pko/lunar-product-videos` @dev
- `lunarphp/lunar` ^1.0
- `phpoffice/phpspreadsheet` ^2.0 || ^3.0

## Licence

Proprietary — Publiko.
