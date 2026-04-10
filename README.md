# MDE Distribution — Back-office e-commerce

Back-office Laravel 11 + Lunar 1.x + Filament 3 remplaçant PrestaShop 8 pour MDE Distribution (distributeur B2B portails, volets, motorisations, matériaux de construction et domotique).

**Périmètre phase 1 : back-office uniquement.** Front-office, paiement, emails et modules métier MDE (FAB-DIS, SIRET, pricing B2B, enrichissement IA) sont planifiés en phase 2.

Cahier des charges complet : [`cahier-des-charges-mde-laravel.md`](./cahier-des-charges-mde-laravel.md).
Guide de contribution : [`CLAUDE.md`](./CLAUDE.md).

## Stack

- PHP 8.3 (conteneur Sail)
- Laravel 11
- Lunar 1.x (`lunarphp/lunar`)
- Filament 3 (livré par Lunar Admin)
- Filament Shield 3 (`bezhansalleh/filament-shield`) — RBAC
- MySQL 8, Redis 7, Mailpit (dev)
- Laravel Sail pour l'environnement Docker local

## Prérequis

- Docker et Docker Compose v2
- Make (optionnel mais recommandé)
- 4 Go de RAM libres pour le conteneur `mysql`

## Installation

```bash
# 1. Copier la configuration d'exemple
cp .env.example .env

# 2. Première installation complète
make install
```

La commande `make install` exécute :

1. `sail up -d --build` (construction des images + démarrage)
2. `sail artisan migrate --graceful --force`
3. `sail artisan lunar:install` (création du premier staff + seed Lunar de base)
4. `sail artisan shield:install admin --no-interaction`
5. `sail artisan shield:generate --all --panel=admin --no-interaction`
6. `sail artisan db:seed --force` (seeders MDE : 50 produits, 3+ collections, 2 groupes clients, 10 commandes)

> ℹ️ Lors de `lunar:install`, l'installeur demande les identifiants du premier staff admin en interactif. Proposition dev : `admin@mde-distribution.fr` / `password`.

## Accès

- Back-office : <http://localhost/admin>
- Mailpit : <http://localhost:8025>
- MySQL : localhost:3307 (user `sail` / password `password`)
- Redis : localhost:6380

## Commandes utiles

```bash
make up          # démarrer Sail
make down        # arrêter
make shell       # shell conteneur app
make migrate     # migrations
make fresh       # migrate:fresh --seed (reset DB complet)
make seed        # seeders MDE uniquement
make test        # PHPUnit
make lint        # Laravel Pint (PSR-12)
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
