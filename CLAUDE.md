# CLAUDE.md — MDE Distribution (back-office)

Guide à destination de Claude Code pour intervenir sur le projet `ecom-laravel`.

## Mission du projet

Back-office e-commerce remplaçant PrestaShop 8 pour **MDE Distribution** (distributeur B2B matériaux de construction, domotique, portails, volets, automatismes). Phase 1 = back-office uniquement. Le front-office, les emails, les paiements et les modules métier MDE (FAB-DIS, SIRET, pricing B2B, enrichissement IA) sont **hors périmètre** et arriveront en phase 2 sous `packages/mde/*`.

Cahier des charges complet : `cahier-des-charges-mde-laravel.md` à la racine — référence contractuelle.

## Stack

| Composant | Version |
|---|---|
| PHP | 8.3+ (conteneur Sail) |
| Laravel | 11.x |
| Lunar Core / Admin | 1.x (`lunarphp/lunar`) |
| Filament | 3.x (fourni par Lunar Admin) |
| Filament Shield | 3.x (`bezhansalleh/filament-shield`) |
| Livewire | 3.x |
| MySQL | 8.x (Sail) |
| Redis | 7.x (queue + cache + session) |
| Mailpit | dev mail catcher |
| Environnement | Laravel Sail (Docker) |

## Règles non-négociables

1. **Ne jamais modifier `vendor/lunarphp/*`.** Aucune exception. Les mises à jour Lunar doivent rester non-régressives. Toute personnalisation passe par les mécanismes officiels.
2. **Toujours `declare(strict_types=1);`** en tête de fichier PHP.
3. **PSR-12** appliqué via Laravel Pint (`make lint`).
4. **Conventions Laravel** pour nommage modèles, migrations, factories, seeders.
5. Les migrations custom MDE vivent dans `database/migrations/` et utilisent le préfixe de table `mde_` (en phase 2).
6. Les futurs modules métier vivent dans `packages/mde/*` et s'enregistrent comme **Filament Plugin**, pas en modifiant le core.

## Mécanismes d'extension

Trois niveaux autorisés, dans cet ordre de préférence (du plus léger au plus packagé) :

### Niveau 1 — `LunarPanel::panel()` dans `AppServiceProvider::register()`

Utiliser pour : ajouter des pages/resources/widgets custom, configurer navigation groups, brand, path admin, plugins.

Fichier concerné : `app/Providers/AppServiceProvider.php`.

```php
use Filament\Panel;
use Lunar\Admin\Support\Facades\LunarPanel;

LunarPanel::panel(function (Panel $panel): Panel {
    return $panel
        ->path('admin')
        ->brandName('MDE Distribution')
        ->navigationGroups([
            'Catalogue',
            'Commandes',
            'Clients',
            'Marketing',
            'Configuration',
        ])
        ->plugin(FilamentShieldPlugin::make());
})->register();
```

### Niveau 2 — `LunarPanel::extensions()` avec `ResourceExtension`

Utiliser pour : ajouter des champs à une ressource existante (form/table), ajouter relation managers ou pages.

Classes d'extension dans `app/Admin/Filament/Extensions/` (à créer quand nécessaire).

```php
LunarPanel::extensions([
    \Lunar\Admin\Filament\Resources\ProductResource::class => \App\Admin\Filament\Extensions\MdeProductExtension::class,
]);
```

### Niveau 3 — Filament Plugin dans `packages/mde/<module>/` (phase 2+)

Utiliser pour : fonctionnalité packagée réutilisable (ex : import FAB-DIS, validation SIRET). Le plugin expose un `FilamentPlugin` qui s'enregistre via `->plugin(new ModuleMdePlugin())` dans le panel.

## Arborescence clé

```
app/
├── Models/User.php                    ← trait LunarUser + LunarUserInterface
├── Providers/AppServiceProvider.php   ← LunarPanel::panel() + Shield
└── Admin/Filament/Extensions/         ← ResourceExtensions MDE (phase 2+)

config/
├── lunar/*.php                        ← configs Lunar publiées (rarement modifiées)
├── lunar.php                          ← config principale Lunar
└── filament-shield.php                ← config Shield

database/
├── migrations/                        ← Lunar publiées + permission_tables
└── seeders/
    ├── DatabaseSeeder.php
    └── Mde*Seeder.php                 ← 10 seeders thématiques MDE

packages/                              ← modules MDE (phase 2+)
└── mde/

resources/views/vendor/lunar/          ← overrides Blade — à minimiser
```

## Commandes Make (raccourcis Sail)

| Commande | Effet |
|---|---|
| `make install` | Première installation complète (build + migrate + lunar:install + shield + seed) |
| `make up` / `make down` | Démarrer / arrêter Sail |
| `make shell` | Shell dans le conteneur applicatif |
| `make migrate` | Exécuter migrations |
| `make fresh` | `migrate:fresh --seed` (reset DB complet) |
| `make seed` | Lancer les seeders MDE |
| `make test` | Suite PHPUnit |
| `make lint` | Laravel Pint (PSR-12) |
| `make artisan CMD='...'` | Commande artisan arbitraire |
| `make composer CMD='...'` | Commande composer arbitraire |

## Lunar : points d'attention

- **Prix en cents** : tous les prix sont stockés en entier (cents/centimes). `19900` = 199,00 €.
- **`attribute_data`** : champs i18n des produits/collections stockés en JSON via `Lunar\FieldTypes\*` (`Text`, `TranslatedText`, `Number`, `Dropdown`…). Toujours passer une collection de FieldTypes, jamais de strings bruts.
- **Staff vs User** : le staff admin est une table séparée (`lunar_staff`) — **ne pas confondre** avec la table `users` (clients / customers futurs).
- **`php artisan lunar:install`** : crée le premier staff + seed Lunar de base. À ré-exécuter après un `migrate:fresh`.
- **`kalnoy/nestedset`** est épinglé à `6.0.7` — les versions ultérieures utilisent `whenBooted()` qui n'existe pas en Laravel 11.

## Paiements (Lunar Payments)

Le système de paiement Lunar est *driver-based* : chaque "type" de paiement (`cash-in-hand`, `card`…) est défini dans `config/lunar/payments.php` et mappe vers un driver enregistré via `Payments::extend('<driver>', ...)` par un service provider.

### Points clés

- **Ne jamais utiliser Laravel Cashier** pour encaisser des commandes Lunar. Cashier est un moteur d'abonnement SaaS lié à `App\Models\User`, il duplique `lunar_orders` / `lunar_transactions` et casse la cohérence. Réserver Cashier à d'éventuels abonnements MDE dédiés s'ils apparaissent un jour.
- **Transactions Lunar** : tous les encaissements / remboursements doivent transiter par `Lunar\Models\Transaction` (rattachées à `lunar_orders`), et non par des tables tierces.
- **Résolution des drivers** : utiliser `Lunar\Facades\Payments::driver('card')` (singleton via `PaymentManagerInterface`). `app(PaymentManager::class)` instancie une classe fraîche **sans** les creators enregistrés par les addons.

### Drivers installés

| Type Lunar | Driver | Package | Webhook |
|---|---|---|---|
| `cash-in-hand` | `offline` | core | — |
| `card` | `stripe` | `lunarphp/stripe` | `POST /stripe/webhook` |

### Stripe (`lunarphp/stripe`)

- Config : `config/lunar/stripe.php` (`policy`, `webhook_path`, `status_mapping`…)
- Credentials dans `config/services.php` → `'stripe'`
- Env vars : `STRIPE_PK`, `STRIPE_SECRET`, `LUNAR_STRIPE_WEBHOOK_SECRET`
- Webhook URL publique à renseigner dans le Stripe Dashboard : `https://<host>/stripe/webhook`
- Migration dédiée : `lunar_stripe_payment_intents` (table créée par l'addon)

### PayPal (phase 2)

`lunarphp/paypal` existe officiellement (je l'avais raté initialement). À installer quand le besoin est confirmé côté front — attention, il s'enregistre aussi sur le type `card` ce qui entre en conflit avec Stripe, il faudra soit créer un type dédié (`paypal`) soit arbitrer entre les deux.

## Shipping (Lunar Table Rate Shipping)

Les frais de port sont gérés par l'addon officiel **`lunarphp/table-rate-shipping`**. Lunar core ne fournit **aucun** système de zones/méthodes/tarifs — c'est ce package qui publie tables, modèles, drivers et resources Filament.

### Points clés

- **Plugin** : `Lunar\Shipping\ShippingPlugin::make()` enregistré dans `AppServiceProvider::register()` après `FilamentShieldPlugin`.
- **NavigationGroup** : `Expédition` (string ajoutée au `->navigationGroups([...])` entre `Marketing` et `Configuration` pour figer l'ordre).
- **Namespace modèles** : `Lunar\Shipping\Models\{ShippingZone, ShippingMethod, ShippingRate, ShippingExclusionList}` — **pas** `Lunar\Models\*`.
- **Resources Filament** : ShippingZoneResource, ShippingMethodResource, ShippingExclusionListResource (ShippingRate est exposé comme RelationManager de ShippingMethod, pas comme resource top-level → pas de policy Shield dédiée).
- **Drivers inclus** (4) : `ship-by`, `flat-rate`, `free-shipping`, `collection`. Résolution via `Shipping::driver($method->driver)`.

### Structure données (attention, contre-intuitif)

- `ShippingMethod` porte `driver` (string) + `data` (cast `AsArrayObject` → config driver-specific).
- `ShippingRate` **ne porte ni `driver` ni `data`** — juste la jonction `shipping_method_id` + `shipping_zone_id` + `enabled`.
- Les **brackets tarifaires** sont stockés via le trait `HasPrices` → table `lunar_prices` morphée, avec `min_quantity` comme seuil déclencheur. Le driver `ship-by` fait `Pricing::for($rate)->qty($tier)->get()` où `$tier` = somme poids × qty (si `charge_by=weight`) ou sous-total (si `charge_by=cart_total`).
- Le driver `free-shipping` lit `data.minimum_spend` qui peut être un int ou un array keyed par code devise (`['EUR' => 50000]`).

### Seed MDE de base

`MdeShippingSeeder` (lancé après `MdeTaxSeeder` dans `DatabaseSeeder`) crée :

- 1 zone `France métropolitaine` (type `country`, rattachée à FR)
- 3 méthodes : `mde-standard` (ship-by par poids), `mde-pickup` (collection, retrait entrepôt), `mde-free` (free-shipping dès 500 €)
- 3 rates attachés avec brackets : 4 paliers pour le standard (690/990/1490/1990 cents), 1 bracket à 0 pour pickup + free

### Phase 2 — Chronopost + Colissimo (réalisée)

Intégration SOAP de **Chronopost** et **Colissimo** via 3 packages sous `packages/mde/` :

| Package | Namespace | Rôle |
|---|---|---|
| `shipping-common` | `Mde\ShippingCommon\` | Contracts, DTOs, modèle `CarrierShipment`, Job, Observer, ZoneResolver, WeightCalculator, resource Filament « Envois transporteurs » |
| `shipping-chronopost` | `Mde\ShippingChronopost\` | Client SOAP (SDK `ladromelaboratoire/chronopostws`), `ChronopostModifier`, page Filament « Configuration Chronopost » |
| `shipping-colissimo` | `Mde\ShippingColissimo\` | Client SOAP (SDK `wsdltophp/package-colissimo-postage`), `ColissimoModifier`, page Filament « Configuration Colissimo » |

**Flux** :

1. **Checkout** : les deux `ShippingModifier` custom (enregistrés via `ShippingModifiers::add()`) injectent des `ShippingOption` dans le manifest à partir de grilles **statiques** en config (pas d'appel API au checkout — latence & résilience). Identifiers : `chronopost.{service}` / `colissimo.{service}`.
2. **Zone** : `ZoneResolver::isMetropole()` filtre France métropolitaine uniquement (skip Corse `20*`, DOM `971`–`978`, étranger).
3. **Poids** : `WeightCalculator::fromCart()` / `fromOrder()` normalise en kg (accepte `kg`/`g`/`lb`, throw sur unité inconnue).
4. **Post-paiement** : `OrderShipmentObserver::updated()` observe `Order::payment_status` → transition vers `paid` → dispatche `CreateCarrierShipmentJob(order_id, carrier, service_code)`.
5. **Job async** : `$tries = 5`, `$backoff = [60, 300, 900, 3600, 14400]`. Appelle le `CarrierClient` résolu via `app("mde.shipping.carrier.{$carrier}")`, persiste le `CarrierShipment` (table `mde_carrier_shipments`), sauvegarde le PDF dans `storage/app/labels/{order_id}/{carrier}-{tracking}.pdf`. Après 5 échecs → `status = 'failed'` + `error_message`.
6. **Admin** : resource Filament `CarrierShipmentResource` (groupe **Expédition**) → liste, filtre par carrier/statut, action « Télécharger étiquette », action « Relancer » pour les échecs.

**Config** : grilles tarifaires dans `config/chronopost.php` et `config/colissimo.php` (paliers poids → prix en cents). **Swap vers quote temps réel** = remplacer l'impl de `CarrierClient::quote()` dans le Client — interface stable, modifiers et job inchangés.

**Dépendances** :
- `ladromelaboratoire/chronopostws` (SOAP Chronopost ShippingServiceWS v4)
- `wsdltophp/package-colissimo-postage` (SOAP Colissimo SlsServiceWS `generateLabel`)
- **Extension PHP `ext-soap` requise** — installée dans le conteneur `app` à l'exécution. À figer dans le Dockerfile au prochain rebuild pour persister (`apt-get install libxml2-dev && docker-php-ext-install soap`).

**Env vars** :

```
# Credentials (vides par défaut, renseigner en prod)
CHRONOPOST_ACCOUNT=
CHRONOPOST_PASSWORD=
CHRONOPOST_SUB_ACCOUNT=
COLISSIMO_CONTRACT=
COLISSIMO_PASSWORD=
COLISSIMO_WSDL_URL=https://ws.colissimo.fr/sls-ws/SlsServiceWS?wsdl

# Adresse expéditeur (partagée Chronopost + Colissimo, valeurs de test en local)
MDE_SHIPPER_NAME, MDE_SHIPPER_STREET, MDE_SHIPPER_ZIP, MDE_SHIPPER_CITY,
MDE_SHIPPER_COUNTRY, MDE_SHIPPER_PHONE, MDE_SHIPPER_EMAIL
```

**Règle** : ne jamais modifier les SDK vendor. Toute personnalisation dans les `Client` des packages `shipping-chronopost` / `shipping-colissimo`.

### Hors scope shipping

- Points relais (`BPR` Colissimo, `Chrono Relais`)
- Tracking webhook (polling ou push transporteur)
- Retour / annulation d'envoi (`cancelSkybill`)
- Livraison hors France métropolitaine (Corse, DOM, étranger)
- Sendcloud (alternative SaaS écartée pour coût)

## Filament Shield

- Panel ID Lunar = `admin`
- Installation : `make install` lance déjà `shield:install admin` et `shield:generate --all --panel=admin`
- Rôles suggérés : `super_admin`, `catalogue_manager`, `sav`, `lecture_seule`
- Les policies sont générées automatiquement pour toutes les ressources Lunar découvertes

## Tests

- PHPUnit 11. Suite : `make test`
- Tests feature minimaux fournis :
  - `tests/Feature/AdminPanelAccessTest.php` — guard redirect, login page OK
  - `tests/Feature/SeedersTest.php` — vérifie 50 produits / ≥3 collections / 2 groupes clients / 10 commandes / ≥5 marques + zone shipping FR / 3 méthodes / 3 rates
- Ajouter des tests pour tout pipeline critique : calcul de prix, stock, cycle de vie commande.

## Ce qu'il faut **éviter**

- Créer des Filament Resources custom en phase 1 : Lunar en fournit déjà pour produits, variantes, collections, prix, commandes, clients, taxes, promos, livraison, marques, tags, canaux, devises, staff.
- Toucher aux fichiers `config/lunar/*.php` sans raison — les conserver proches du default facilite les mises à jour.
- Modifier les migrations Lunar publiées — si besoin de champs additionnels, créer une migration MDE dédiée qui ajoute des colonnes (`Schema::table`).

## Documentation externe

- Lunar : <https://docs.lunarphp.io>
- Filament : <https://filamentphp.com/docs/3.x>
- Laravel 11 : <https://laravel.com/docs/11.x>
- Filament Shield : <https://filamentphp.com/plugins/bezhansalleh-shield>

## Conventions Git

- Branches : `main` (prod), `develop` (intégration), `feature/*` par module
- Conventional Commits recommandés (`feat:`, `fix:`, `refactor:`, `chore:`…)
- Pas de mention IA dans les messages de commit
