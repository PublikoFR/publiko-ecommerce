# Back-office e-commerce Laravel + Lunar

Back-office e-commerce B2B basé sur **Laravel 11 + Lunar 1.x + Filament 3**, livré avec des modules métier réutilisables (shipping Chronopost/Colissimo, features catalogue, fidélité, authentification client pro, CMS storefront, importeur IA, store locator, listes d'achat, quick order).

Le branding (nom de la boutique, accroche, description SEO, logo) est entièrement paramétrable via le back-office (**Storefront → Paramètres → Identité**). Aucune marque n'est codée en dur.

Cahier des charges : [`cahier-des-charges.md`](./cahier-des-charges.md).
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

1. `docker compose up -d --build`
2. `composer install` dans le conteneur `app`
3. Correction des permissions `storage/` et `bootstrap/cache/`
4. `php artisan key:generate --force`
5. `php artisan migrate --graceful --force`
6. `php artisan lunar:install`
7. `php artisan shield:install admin --no-interaction`
8. `php artisan shield:generate --all --panel=admin --no-interaction`
9. `php artisan db:seed --force` (seeders de démo : 50 produits, 3+ collections, 2 groupes clients, 10 commandes)

## Commandes utiles

```bash
make up          # démarrer les conteneurs
make down        # arrêter
make shell       # bash dans le conteneur app (user sail)
make migrate     # migrations
make fresh       # migrate:fresh --seed (reset DB complet)
make seed        # seeders uniquement
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
└── Filament/Extensions/               # ResourceExtensions

config/
├── lunar/*.php                        # configs Lunar publiées
└── filament-shield.php

database/
├── migrations/
└── seeders/                           # seeders de démo

packages/pko/                          # modules métier (namespace interne Pko\)

cahier-des-charges.md                  # spec contractuelle
CLAUDE.md                              # guide Claude Code / conventions
```

## Principes d'extension

1. **Jamais** modifier `vendor/lunarphp/*`
2. Personnalisation niveau 1 : `LunarPanel::panel()` dans `AppServiceProvider`
3. Personnalisation niveau 2 : `LunarPanel::extensions([Resource::class => Extension::class])`
4. Personnalisation niveau 3 : Filament Plugin dans `packages/pko/*`

Détails dans [`CLAUDE.md`](./CLAUDE.md).

## Tests

```bash
make test
```
