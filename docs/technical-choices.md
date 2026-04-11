# Choix techniques — MDE Distribution (back-office)

Document de référence pour tous les arbitrages techniques effectués sur le projet `ecom-laravel`. Mis à jour au fil des chantiers.

> **Contexte global** : remplacement du back-office PrestaShop 8 de MDE Distribution (distributeur B2B matériaux de construction, domotique, portails, volets, automatismes) par un back-office Laravel + Lunar. Phase 1 = back-office pur, phases ultérieures = front, modules métier, intégrations externes.

---

## 1. Stack principale

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

## 2. Environnement Docker

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

## 3. Architecture d'extension Lunar

Trois niveaux d'extension autorisés, dans cet ordre de préférence (du plus léger au plus packagé) :

### Niveau 1 — `LunarPanel::panel()` dans `AppServiceProvider::register()`

Pour ajouter pages/resources/widgets custom, configurer navigation groups, brand, path admin, plugins.

```php
LunarPanel::panel(function (Panel $panel): Panel {
    return $panel
        ->path('admin')
        ->brandName('MDE Distribution')
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

### Niveau 3 — Filament Plugin dans `packages/mde/<module>/`

Pour une fonctionnalité packagée réutilisable (Chronopost, Colissimo, futurs modules FAB-DIS, SIRET, etc.). Le plugin expose un `FilamentPlugin` enregistré via `->plugin(new ModuleMdePlugin())` dans le panel.

**Règle** : les futurs modules métier vivent dans `packages/mde/*` et s'enregistrent comme Filament Plugin, **pas** en modifiant le core.

---

## 4. Paiements

### 4.1 Choix : Lunar Payments natif + addon Stripe officiel

**Décision** : le système de paiement Lunar est driver-based. Chaque type de paiement (`cash-in-hand`, `card`…) est défini dans `config/lunar/payments.php` et mappe vers un driver enregistré via `Payments::extend('<driver>', ...)`.

**Drivers installés** :

| Type Lunar | Driver | Package | Webhook |
|---|---|---|---|
| `cash-in-hand` | `offline` | core | — |
| `card` | `stripe` | `lunarphp/stripe` | `POST /stripe/webhook` |

### 4.2 Rejet de Laravel Cashier

**Pourquoi pas Cashier** :

- Cashier est un moteur d'**abonnement SaaS** lié à `App\Models\User`, pas un encaisseur de commandes.
- Il duplique les tables Lunar (`lunar_orders`, `lunar_transactions`) et casse la cohérence.
- Les transactions Stripe doivent transiter par `Lunar\Models\Transaction` pour que l'historique commande reste unifié.

**Règle** : Cashier réservé à d'éventuels abonnements MDE dédiés s'ils apparaissent un jour (produits par abonnement, facturation récurrente). Jamais pour encaisser une commande.

### 4.3 Stripe (lunarphp/stripe)

- Config : `config/lunar/stripe.php` (`policy`, `webhook_path`, `status_mapping`…)
- Credentials dans `config/services.php` → `'stripe'`
- Env vars : `STRIPE_PK`, `STRIPE_SECRET`, `LUNAR_STRIPE_WEBHOOK_SECRET`
- Webhook URL publique à renseigner dans le Stripe Dashboard : `https://<host>/stripe/webhook`
- Migration dédiée : `lunar_stripe_payment_intents`
- Page admin dédiée : `app/Filament/Pages/StripeConfig.php` (groupe **Configuration**) avec bouton « Tester la connexion » qui appelle `StripeClient::balance->retrieve()`.

### 4.4 PayPal (phase 2)

`lunarphp/paypal` existe officiellement. À installer quand le besoin est confirmé côté front. **Attention** : il s'enregistre aussi sur le type `card`, ce qui entre en conflit avec Stripe. Options :

- Créer un type dédié `paypal` dans `config/lunar/payments.php`
- Arbitrer entre Stripe et PayPal

---

## 5. Shipping

### 5.1 Phase 1 — Table Rate Shipping (`lunarphp/table-rate-shipping`)

**Décision** : utiliser l'addon officiel plutôt qu'un développement maison. Lunar core ne fournit aucun système de zones/méthodes/tarifs.

**Points clés** :

- Plugin `Lunar\Shipping\ShippingPlugin::make()` enregistré dans `AppServiceProvider::register()`.
- NavigationGroup **Expédition** ajouté entre `Marketing` et `Configuration`.
- Namespace modèles : `Lunar\Shipping\Models\{ShippingZone, ShippingMethod, ShippingRate, ShippingExclusionList}`.
- 4 drivers inclus : `ship-by`, `flat-rate`, `free-shipping`, `collection`.

**Structure de données** (contre-intuitive) :

- `ShippingMethod` porte `driver` (string) + `data` (cast `AsArrayObject` → config driver-specific).
- `ShippingRate` **ne porte ni `driver` ni `data`** — juste la jonction `shipping_method_id` + `shipping_zone_id` + `enabled`.
- Brackets tarifaires stockés via le trait `HasPrices` → table `lunar_prices` morphée, avec `min_quantity` comme seuil déclencheur.
- Le driver `free-shipping` lit `data.minimum_spend` qui peut être un int ou un array keyed par code devise (`['EUR' => 50000]`).

**Seed MDE de base** (`MdeShippingSeeder`) :

- 1 zone `France métropolitaine` (type `country`, rattachée à FR)
- 3 méthodes : `mde-standard` (ship-by par poids), `mde-pickup` (collection, retrait entrepôt), `mde-free` (free-shipping dès 500 €)
- 3 rates attachés avec brackets : 4 paliers pour le standard (690/990/1490/1990 cents), 1 bracket à 0 pour pickup + free

### 5.2 Phase 2 — Chronopost + Colissimo dynamiques

**Décision** : intégration SOAP de **Chronopost** et **Colissimo** via 3 packages sous `packages/mde/`. Sendcloud écarté pour coût SaaS.

**Architecture** :

| Package | Namespace | Rôle |
|---|---|---|
| `shipping-common` | `Mde\ShippingCommon\` | Contracts, DTOs, modèle `CarrierShipment`, Job, Observer, `ZoneResolver`, `WeightCalculator`, resource Filament « Envois transporteurs » |
| `shipping-chronopost` | `Mde\ShippingChronopost\` | Client SOAP (SDK `ladromelaboratoire/chronopostws`), `ChronopostModifier`, page Filament « Configuration Chronopost » |
| `shipping-colissimo` | `Mde\ShippingColissimo\` | Client SOAP (SDK `wsdltophp/package-colissimo-postage`), `ColissimoModifier`, page Filament « Configuration Colissimo » |

**Choix critique — grilles statiques vs API temps réel au checkout** :

- **Choix** : grilles statiques versionnées dans `config/chronopost.php` / `config/colissimo.php`.
- **Raison** : les contrats La Poste sont des grilles annuelles connues. Un appel API QuickCost ajouterait 300–800 ms de latence au checkout et risquerait de le casser en cas d'incident SOAP La Poste.
- **Swap vers temps réel** : remplacer la méthode `CarrierClient::quote()` dans le Client concerné. Interface stable, modifiers et job inchangés.

**Flux** :

1. **Checkout** : les deux `ShippingModifier` custom (enregistrés via `ShippingModifiers::add()`) injectent des `ShippingOption` dans le manifest à partir des grilles statiques. Identifiers : `chronopost.{service}` / `colissimo.{service}`.
2. **Zone** : `ZoneResolver::isMetropole()` filtre France métropolitaine uniquement (skip Corse `20*`, DOM `971`–`978`, étranger).
3. **Poids** : `WeightCalculator::fromCart()` / `fromOrder()` normalise en **kg** (accepte `kg`/`g`/`lb`, throw sur unité inconnue).
4. **Post-paiement** : `OrderShipmentObserver::updated()` observe `Order::payment_status` → transition vers `paid` → dispatche `CreateCarrierShipmentJob(order_id, carrier, service_code)`.
5. **Job async** : `$tries = 5`, `$backoff = [60, 300, 900, 3600, 14400]`. Résout le `CarrierClient` via `app("mde.shipping.carrier.{$carrier}")`, persiste le `CarrierShipment` (table `mde_carrier_shipments`), sauvegarde le PDF dans `storage/app/labels/{order_id}/{carrier}-{tracking}.pdf`. Après 5 échecs → `status = 'failed'` + `error_message`.
6. **Admin** : resource Filament `CarrierShipmentResource` (groupe **Expédition**) → liste, filtre par carrier/statut, action « Télécharger étiquette », action « Relancer » pour les échecs.

**Dépendances vendor** :

- `ladromelaboratoire/chronopostws` — SOAP Chronopost ShippingServiceWS v4
- `wsdltophp/package-colissimo-postage` — SOAP Colissimo SlsServiceWS `generateLabel`
- **Extension PHP `ext-soap`** — installée dans le conteneur `app` à l'exécution. À figer dans le Dockerfile au prochain rebuild (`apt-get install libxml2-dev && docker-php-ext-install soap`).

**Pourquoi SOAP et pas REST** : les API La Poste (Chronopost + Colissimo) ne proposent pas d'API REST publique pour la création d'étiquettes. Le protocole historique reste SOAP, avec WSDL fourni. Pas d'alternative.

**Décisions tranchées** :

| Question | Choix | Raison |
|---|---|---|
| Unité de poids | kg | `WeightCalculator` normalise tout en kg (conversion `g/1000` si `weight_unit='g'`). |
| Zone de livraison v1 | France métropolitaine uniquement | Skip Corse/DOM/étranger pour éviter les grilles tarifaires multi-zones complexes. |
| Déclenchement Job | Après paiement confirmé | Pas à la création brute de l'Order (qui peut être `draft`). Vérif via `wasChanged('payment_status') && payment_status === 'paid'`. |
| Retry API | 5 tentatives, backoff exponentiel `[60, 300, 900, 3600, 14400]` | Couvre rate-limiting et pannes temporaires La Poste. Après 5 échecs → marqué `failed` + notification admin. |
| Storage étiquettes | Disque `local` (`storage/app/labels/…`) | Pas besoin de S3 en phase 1. Filament fournit un download. |
| Services Chronopost activés | `13` (Chrono 13) + `02` (Chrono Classic) | Les plus courants. Modifiables dans `config/chronopost.php`. |
| Services Colissimo activés | `DOM` (sans signature) + `DOS` (avec signature) | Idem, modifiables dans `config/colissimo.php`. |

### 5.3 Hors scope shipping

- Points relais (`BPR` Colissimo, `Chrono Relais`)
- Tracking webhook (polling ou push transporteur)
- Retour / annulation d'envoi (`cancelSkybill`)
- Livraison hors France métropolitaine (Corse, DOM, étranger)
- Sendcloud (alternative SaaS écartée pour coût)

---

## 6. Autorisations (RBAC)

**Décision** : `bezhansalleh/filament-shield` 3.x.

**Pourquoi** :

- Plugin Filament 3 officiel qui génère automatiquement les policies pour toutes les ressources Lunar découvertes.
- UI admin pour créer rôles + permissions.
- Scope par `panel_id = 'admin'` (séparation claire des permissions front vs back-office futur).

**Rôles suggérés** :

- `super_admin` — accès total
- `catalogue_manager` — produits, catégories, marques, taxes, médias
- `sav` — commandes, clients, retours
- `lecture_seule` — lecture uniquement pour audit/reporting

**Installation automatisée** : `make install` lance `shield:install admin` puis `shield:generate --all --panel=admin` pour générer toutes les policies d'un coup.

---

## 7. Lunar — points d'attention

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

---

## 8. Tests

**Framework** : PHPUnit 11 (pas Pest — volonté d'avoir une syntaxe unique avec le reste de l'écosystème Laravel/Lunar).

**Organisation** :

- `tests/Unit/` — tests sans Laravel bootstrap (helpers purs, DTOs, calculs)
- `tests/Feature/` — tests avec `RefreshDatabase` (routes, seeders, jobs)

**Tests livrés** :

- `AdminPanelAccessTest` — guard redirect sur `/admin`, login page accessible
- `SeedersTest` — vérifie 50 produits / ≥3 collections / 2 groupes clients / 10 commandes / ≥5 marques + zone shipping FR / 3 méthodes / 3 rates
- `Unit\Shipping\ZoneResolverTest` — cas France métropolitaine, Corse, DOM, étranger, input invalide
- `Unit\Shipping\ChronopostQuoteTest` — grille tarifaire, services activés, max weight
- `Unit\Shipping\ColissimoQuoteTest` — grille + surcharge signature DOS

**Règle** : ajouter des tests pour tout pipeline critique (calcul de prix, stock, cycle de vie commande, création d'envoi transporteur).

---

## 9. Arborescence clé

```
app/
├── Models/User.php                    ← trait LunarUser + LunarUserInterface
├── Providers/AppServiceProvider.php   ← LunarPanel::panel() + Shield + shipping plugins
├── Filament/Pages/StripeConfig.php    ← page admin Stripe (groupe Configuration)
├── Admin/Filament/Extensions/         ← ResourceExtensions MDE (phase 2+, vide actuellement)
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
    └── Mde*Seeder.php                 ← 10 seeders thématiques MDE (currency, channel, language, country, tax, shipping, customer group, brand, collection, product type, product, customer, order)

packages/mde/
├── shipping-common/                   ← Mde\ShippingCommon\
├── shipping-chronopost/               ← Mde\ShippingChronopost\
└── shipping-colissimo/                ← Mde\ShippingColissimo\

resources/views/
├── filament/pages/                    ← Blade pour pages custom (stripe-config)
└── vendor/lunar/                      ← overrides Blade — à minimiser

tests/
├── Feature/
└── Unit/Shipping/
```

---

## 10. Conventions Git

- Branches : `main` (prod), `develop` (intégration), `feature/*` par module
- **Conventional Commits** (`feat:`, `fix:`, `refactor:`, `chore:`, `docs:`…)
- **Pas de mention IA** dans les messages de commit (pas de `Co-Authored-By: Claude`, pas d'emoji generator, pas de référence à Anthropic)

---

## 11. Ce qu'il faut **éviter**

- Créer des Filament Resources custom en phase 1 : Lunar fournit déjà des resources pour produits, variantes, collections, prix, commandes, clients, taxes, promos, livraison, marques, tags, canaux, devises, staff.
- Toucher à `config/lunar/*.php` sans raison — conserver ces fichiers proches du default facilite les mises à jour.
- Modifier les migrations Lunar publiées — si besoin de champs additionnels, créer une migration MDE dédiée qui ajoute des colonnes (`Schema::table`).
- Modifier les SDK vendor (Chronopost, Colissimo, Stripe) — toute personnalisation dans les `Client` de nos packages.
- Utiliser Cashier pour encaisser une commande.
- Committer en sautant les hooks (`--no-verify`) ou sans strict_types.

---

## 12. Documentation externe

- [Lunar](https://docs.lunarphp.io)
- [Filament 3](https://filamentphp.com/docs/3.x)
- [Laravel 11](https://laravel.com/docs/11.x)
- [Filament Shield](https://filamentphp.com/plugins/bezhansalleh-shield)
- [lunarphp/stripe](https://github.com/lunarphp/stripe)
- [lunarphp/table-rate-shipping](https://github.com/lunarphp/table-rate-shipping)

---

_Dernière mise à jour : 2026-04-11 — phase 2 shipping (Chronopost + Colissimo) livrée._
