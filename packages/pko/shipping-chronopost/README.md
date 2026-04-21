# pko/lunar-shipping-chronopost

Driver Chronopost (SOAP) pour Lunar : calcul tarifs en temps réel, génération étiquettes PDF, suivi colis.

## Installation

```bash
composer require pko/lunar-shipping-chronopost
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-shipping-chronopost-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `pko/lunar-shipping-common` @dev
- `ladromelaboratoire/chronopostws` ^0.0.13

## Licence

Proprietary — Publiko.
