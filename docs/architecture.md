# Architecture — ecom-laravel

Stack technique, environnement Docker, mécanismes d'extension Lunar, gotchas, arborescence.

## Stack principale

| Composant | Version | Raison du choix |
|---|---|---|
| **PHP** | 8.3+ | PHP 8.2 minimum requis par Laravel 11 ; 8.3 dans le conteneur pour profiter des readonly properties et du JIT. `strict_types` obligatoire partout. |
| **Laravel** | 11.x | LTS à l'horizon 2027, `bootstrap/providers.php` simplifié, bootstrap modulaire via `Application::configure`. |
| **Lunar Core / Admin** | 1.x | Headless e-commerce 100% Laravel-native, architecture Filament, multi-canal natif, système `attribute_data` flexible. Alternative Bagisto écartée (Symfony) pour rester dans l'écosystème Laravel. |
| **Filament** | 3.x | Fourni par Lunar Admin. Écosystème riche (policies, tables, forms), v4 encore immature au moment du choix. |
| **Filament Shield** | 3.x | Plugin officiel RBAC pour Filament. Génère automatiquement les policies Lunar + UI admin pour rôles/permissions. |
| **Livewire** | 3.x | Moteur interactif de Filament. |
| **MySQL** | 8.x | Compatible Lunar (SQLite en test uniquement), full-text search FR natif, JSON types performants. |
| **Redis** | 7.x | Queue + cache + session dans un seul service (compose simple). |
| **Mailpit** | dev | Catcher SMTP local pour le développement et les tests manuels. |

**Règles non-négociables** :

1. **Ne jamais modifier `vendor/lunarphp/*`**. Les mises à jour Lunar doivent rester non-régressives. Toute personnalisation passe par les mécanismes officiels d'extension.
2. **`declare(strict_types=1);`** en tête de chaque fichier PHP.
3. **PSR-12** via Laravel Pint (`make lint`).
4. **Conventions Laravel** pour nommage (modèles, migrations, factories, seeders).

---

## Environnement Docker

**Choix** : stack Docker custom avec **Traefik** + **phpMyAdmin** (remplace Laravel Sail).

**Raisons** :

- Traefik sert simultanément plusieurs projets locaux sur `.localhost` sans conflit de ports (pas besoin de mapper 8000/8001/8002…).
- phpMyAdmin intégré évite d'installer un client MySQL local.
- Maintien de la compatibilité avec les commandes `composer`, `artisan`, `php`, etc.

**Services** (`compose.yaml`) :

- `app` — PHP-FPM 8.3 + Nginx, exposé via Traefik sur `mde-laravel.localhost`
- `mysql` — MySQL 8.0 avec healthcheck, volume persistant
- `redis` — Redis 7-alpine
- `phpmyadmin` — exposé via `pma.mde-laravel.localhost`

**Commandes raccourcies** dans `Makefile` :

| Commande | Effet |
|---|---|
| `make install` | Première installation complète (build + migrate + `lunar:install` + `shield:install` + seed) |
| `make up` / `make down` | Démarrer / arrêter la stack |
| `make shell` | Shell interactif dans le conteneur `app` |
| `make fresh` | `migrate:fresh --seed` (reset DB complet) |
| `make test` | Suite PHPUnit |
| `make lint` | Laravel Pint |
| `make artisan CMD='...'` | Commande artisan arbitraire |
| `make composer CMD='...'` | Commande composer arbitraire |

---


## Architecture d'extension Lunar

Trois niveaux d'extension autorisés, dans cet ordre de préférence (du plus léger au plus packagé) :

### Niveau 1 — `LunarPanel::panel()` dans `AppServiceProvider::register()`

Pour ajouter pages/resources/widgets custom, configurer navigation groups, brand, path admin, plugins.

```php
LunarPanel::panel(function (Panel $panel): Panel {
    return $panel
        ->path('admin')
        ->brandName(brand_name())
        ->navigationGroups([
            'Catalogue', 'Commandes', 'Clients', 'Marketing', 'Expédition', 'Configuration',
        ])
        ->plugin(FilamentShieldPlugin::make())
        ->plugin(ShippingPlugin::make())
        ->plugin(ShippingCommonPlugin::make())
        ->plugin(ChronopostPlugin::make())
        ->plugin(ColissimoPlugin::make());
})->register();
```

### Niveau 2 — `LunarPanel::extensions()` avec `ResourceExtension`

Pour ajouter des champs à une ressource existante (form/table), ajouter relation managers ou pages. Classes d'extension dans `app/Admin/Filament/Extensions/`.

### Niveau 3 — Filament Plugin dans `packages/pko/<module>/`

Pour une fonctionnalité packagée réutilisable (Chronopost, Colissimo, futurs modules FAB-DIS, SIRET, etc.). Le plugin expose un `FilamentPlugin` enregistré via `->plugin(new CustomPlugin())` dans le panel.

**Règle** : les futurs modules métier vivent dans `packages/pko/*` et s'enregistrent comme Filament Plugin, **pas** en modifiant le core.

---


## Lunar — points d'attention

### 7.1 Prix en cents

Tous les prix Lunar sont stockés en **entier** (cents/centimes). `19900` = 199,00 €. Pas de float pour les montants.

### 7.2 `attribute_data` i18n

Champs internationalisables des produits/collections stockés en JSON via `Lunar\FieldTypes\*` (`Text`, `TranslatedText`, `Number`, `Dropdown`…).

**Règle** : toujours passer une **collection de FieldTypes**, jamais de strings bruts.

### 7.3 Staff vs User

Le staff admin est une table séparée (`lunar_staff`). **Ne pas confondre** avec la table `users` (clients/customers futurs).

- Création d'un staff : `php artisan lunar:create-admin --firstname=... --lastname=... --email=... --password=...`
- À ré-exécuter après un `migrate:fresh` (la table est wipée).

### 7.4 Épinglage `kalnoy/nestedset`

`kalnoy/nestedset` est épinglé à **`6.0.7`**. Les versions ultérieures utilisent `whenBooted()` qui n'existe pas en Laravel 11.

### 7.5 Slugs produits — `PkoProductUrlGenerator`

**Format** : `{brand-slug}-{name-slug}-{mpn-slug}` (ex : `somfy-boitier-axroll-1822143`).

- **brand** : `$product->brand?->name` (nullable)
- **name** : `$product->translateAttribute('name')` (champ i18n Lunar)
- **mpn** : `$product->variants()->first()->mpn` (Manufacturer Part Number) — **uniquement si 1 seule variante**. Produits multi-variantes (sur-mesure menuiseries/portes) → fallback `brand-name` + suffixe numérique auto en cas de collision.

**Mapping références Lunar** :

| Colonne Lunar (`product_variants`) | Sens métier |
|---|---|
| `mpn` | Référence fabricant (utilisée dans le slug) |
| `sku` | Référence interne |
| `ean` | Code EAN |

**Timing de génération** : Lunar appelle le generator sur l'event `Product::created`, avant que les variants existent. MPN pas encore connu à ce moment. Solution : `PkoProductUrlGenerator::regenerate()` re-calcule le slug et crée une nouvelle URL `default=true` si différent de l'actuel. Déclenché sur `ProductVariant::saved` via un observer dans `AppServiceProvider::boot()`.

**Historique SEO** : Lunar auto-démote l'ancienne URL en `default=false`. L'ancien slug reste actif et résout le même produit (pas de 404, pas de redirect explicite). Utile si le nom ou le MPN change après import.

**Config** : `config/lunar/urls.php` → `'generator' => App\Generators\PkoProductUrlGenerator::class`.

---


## Arborescence clé

```
app/
├── Models/User.php                    ← trait LunarUser + LunarUserInterface
├── Providers/AppServiceProvider.php   ← LunarPanel::panel() + Shield + shipping plugins
├── Filament/Pages/StripeConfig.php    ← page admin Stripe (groupe Configuration)
├── Admin/Filament/Extensions/         ← ResourceExtensions (phase 2+, vide actuellement)
└── Policies/                          ← Shield-generated policies + custom

config/
├── lunar/*.php                        ← configs Lunar publiées (rarement modifiées)
├── lunar/stripe.php                   ← config Stripe
├── lunar/payments.php                 ← mapping type → driver
├── filament-shield.php                ← config Shield
└── services.php                       ← Stripe credentials

database/
├── migrations/                        ← Lunar publiées + permission_tables + Stripe
└── seeders/
    ├── DatabaseSeeder.php             ← orchestrator
    └── Pko*Seeder.php                 ← 10 seeders thématiques de démo (currency, channel, language, country, tax, shipping, customer group, brand, collection, product type, product, customer, order)

packages/pko/
├── shipping-common/                   ← Pko\ShippingCommon\
├── shipping-chronopost/               ← Pko\ShippingChronopost\
├── shipping-colissimo/                ← Pko\ShippingColissimo\
└── catalog-features/                  ← Pko\CatalogFeatures\ (familles + valeurs + pivot produit)

resources/views/
├── filament/pages/                    ← Blade pour pages custom (stripe-config)
└── vendor/lunar/                      ← overrides Blade — à minimiser

tests/
├── Feature/
└── Unit/Shipping/
```

---


## Ce qu'il faut **éviter**

- Créer des Filament Resources custom en phase 1 : Lunar fournit déjà des resources pour produits, variantes, collections, prix, commandes, clients, taxes, promos, livraison, marques, tags, canaux, devises, staff.
- Toucher à `config/lunar/*.php` sans raison — conserver ces fichiers proches du default facilite les mises à jour.
- Modifier les migrations Lunar publiées — si besoin de champs additionnels, créer une migration custom dédiée qui ajoute des colonnes (`Schema::table`).
- Modifier les SDK vendor (Chronopost, Colissimo, Stripe) — toute personnalisation dans les `Client` de nos packages.
- Utiliser Cashier pour encaisser une commande.
- Committer en sautant les hooks (`--no-verify`) ou sans strict_types.

---

