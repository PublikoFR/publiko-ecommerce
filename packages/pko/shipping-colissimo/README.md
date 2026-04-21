# pko/lunar-shipping-colissimo

Driver Colissimo (SOAP) pour Lunar : calcul tarifs en temps réel, génération étiquettes PDF, suivi colis.

## Installation

```bash
composer require pko/lunar-shipping-colissimo
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-shipping-colissimo-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `pko/lunar-shipping-common` @dev
- `wsdltophp/package-colissimo-postage` ^3.0

## Licence

Proprietary — Publiko.
