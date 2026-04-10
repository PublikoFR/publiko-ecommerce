# MDE Distribution — Back-office e-commerce

Back-office Laravel 11 + Lunar 1.x + Filament 3 remplaçant PrestaShop 8 pour MDE Distribution (distributeur B2B portails, volets, motorisations, matériaux de construction et domotique).

**Périmètre phase 1 : back-office uniquement.** Front-office, paiement, emails et modules métier MDE (FAB-DIS, SIRET, pricing B2B, enrichissement IA) sont planifiés en phase 2.

Cahier des charges complet : [`cahier-des-charges-mde-laravel.md`](./cahier-des-charges-mde-laravel.md).
Guide de contribution : [`CLAUDE.md`](./CLAUDE.md).

## Stack

- PHP 8.3 + Apache (conteneur custom basé sur `php:8.3-apache`)
- Laravel 11
- Lunar 1.x (`lunarphp/lunar`)
- Filament 3 (livré par Lunar Admin)
- Filament Shield 3 (`bezhansalleh/filament-shield`) — RBAC
- MySQL 8, Redis 7, phpMyAdmin
- Mailpit **partagé** (service externe branché sur `traefik_network`)
- Traefik en reverse proxy (réseau externe `traefik_network`)

## Prérequis

- Docker et Docker Compose v2
- Traefik démarré avec un réseau externe nommé `traefik_network`
- Make (optionnel mais recommandé)
- Entrées `/etc/hosts` ou résolution `*.localhost` fonctionnelle
- 4 Go de RAM libres pour le conteneur `mysql`

## Installation

```bash
# 1. Copier la configuration d'exemple
cp .env.example .env

# 2. Première installation complète
make install
```

La commande `make install` exécute :

1. `docker compose up -d --build` (construction de l'image applicative + démarrage)
2. `composer install` dans le conteneur `app`
3. Correction des permissions `storage/` et `bootstrap/cache/`
4. `php artisan key:generate --force`
5. `php artisan migrate --graceful --force`
6. `php artisan lunar:install` (création du premier staff + seed Lunar de base)
7. `php artisan shield:install admin --no-interaction`
8. `php artisan shield:generate --all --panel=admin --no-interaction`
9. `php artisan db:seed --force` (seeders MDE : 50 produits, 3+ collections, 2 groupes clients, 10 commandes)

> ℹ️ Lors de `lunar:install`, l'installeur demande les identifiants du premier staff admin en interactif. Proposition dev : `admin@mde-distribution.fr` / `password`.

## Accès

- Back-office : <http://mde-laravel.localhost/admin>
- phpMyAdmin : <http://pma.mde-laravel.localhost>
- Mailpit : <http://mailpit.localhost> (service partagé)
- MySQL : accessible via phpMyAdmin ou via `make shell` (user `mde` / password `mde_password`, root `root_password`)
- Redis : interne au réseau `backend`

## Commandes utiles

```bash
make up          # démarrer les conteneurs
make down        # arrêter
make shell       # bash dans le conteneur app (user sail)
make migrate     # migrations
make fresh       # migrate:fresh --seed (reset DB complet)
make seed        # seeders MDE uniquement
make test        # PHPUnit
make lint        # Laravel Pint (PSR-12)
make logs        # suivre les logs
make ps          # statut des conteneurs
make permissions # corriger storage/ + bootstrap/cache/
make artisan CMD='tinker'
make composer CMD='dump-autoload'
```

## Structure du projet

```
app/
├── Models/User.php                    # trait LunarUser
├── Providers/AppServiceProvider.php   # configuration LunarPanel + Shield
└── Admin/Filament/Extensions/         # ResourceExtensions MDE (phase 2+)

config/
├── lunar/*.php                        # configs Lunar publiées
└── filament-shield.php

database/
├── migrations/                        # Lunar + spatie/permission
└── seeders/Mde*Seeder.php             # 10 seeders thématiques MDE

packages/mde/                          # futurs modules métier (phase 2)

cahier-des-charges-mde-laravel.md      # spec contractuelle
CLAUDE.md                              # guide Claude Code / conventions
```

## Principes d'extension

1. **Jamais** modifier `vendor/lunarphp/*`
2. Personnalisation niveau 1 : `LunarPanel::panel()` dans `AppServiceProvider`
3. Personnalisation niveau 2 : `LunarPanel::extensions([Resource::class => Extension::class])`
4. Personnalisation niveau 3 : Filament Plugin dans `packages/mde/*`

Détails dans [`CLAUDE.md`](./CLAUDE.md).

## Tests

```bash
make test
```

Suite minimale fournie :

- `tests/Feature/AdminPanelAccessTest.php`
- `tests/Feature/SeedersTest.php` — garantit 50 produits, ≥3 collections, 2 groupes clients, 10 commandes

## Licence

Usage interne MDE Distribution — propriété Publiko.
