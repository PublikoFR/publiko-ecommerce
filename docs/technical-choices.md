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

### 6.1 Credentials admin de dev — source de vérité

Un seul point de création du compte admin : **`MdeAdminUserSeeder`**, premier seeder appelé par `DatabaseSeeder`. Idempotent (`updateOrCreate` par email), il garantit qu'après un `make fresh` ou `make install` le compte admin est toujours restauré avec les mêmes credentials.

Credentials par défaut :
- Email : `admin@mde-distribution.fr`
- Password : `testing123`

Overridables via les variables d'env `MDE_ADMIN_EMAIL` et `MDE_ADMIN_PASSWORD` (utile pour environnements non-locaux).

Le Makefile enchaîne dans `install` et `fresh` : `migrate[:fresh]` → `lunar:install` → `shield:generate` → `db:seed` (crée Staff id=1) → `shield:super-admin --user=1` (assigne le rôle `super_admin`). La commande `lunar:create-admin` n'est plus utilisée (remplacée par le seeder).

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

### 7.5 Slugs produits — `MdeProductUrlGenerator`

**Format** : `{brand-slug}-{name-slug}-{mpn-slug}` (ex : `somfy-boitier-axroll-1822143`).

- **brand** : `$product->brand?->name` (nullable)
- **name** : `$product->translateAttribute('name')` (champ i18n Lunar)
- **mpn** : `$product->variants()->first()->mpn` (Manufacturer Part Number) — **uniquement si 1 seule variante**. Produits multi-variantes (sur-mesure menuiseries/portes) → fallback `brand-name` + suffixe numérique auto en cas de collision.

**Mapping références Lunar ↔ MDE** :

| Colonne Lunar (`product_variants`) | Sens métier MDE |
|---|---|
| `mpn` | Référence fabricant (utilisée dans le slug) |
| `sku` | Référence interne MDE |
| `ean` | Code EAN |

**Timing de génération** : Lunar appelle le generator sur l'event `Product::created`, avant que les variants existent. MPN pas encore connu à ce moment. Solution : `MdeProductUrlGenerator::regenerate()` re-calcule le slug et crée une nouvelle URL `default=true` si différent de l'actuel. Déclenché sur `ProductVariant::saved` via un observer dans `AppServiceProvider::boot()`.

**Historique SEO** : Lunar auto-démote l'ancienne URL en `default=false`. L'ancien slug reste actif et résout le même produit (pas de 404, pas de redirect explicite). Utile si le nom ou le MPN change après import.

**Config** : `config/lunar/urls.php` → `'generator' => App\Generators\MdeProductUrlGenerator::class`.

---

## 7.bis Catalogue — caractéristiques filtrables

### 7.bis.1 Problème

Migration depuis PrestaShop 8 : les produits MDE portent des **caractéristiques filtrables** (marque, matière, usage, diamètre, norme, application…) utilisées pour alimenter le mega-menu et les filtres à facettes sur un catalogue de ~50 000 produits. On doit pouvoir :

- Lister les produits qui matchent **plusieurs** valeurs simultanément (AND multi-familles, OR intra-famille)
- Afficher les compteurs de facettes par collection
- Réordonner les familles et les valeurs depuis le back-office
- Rattacher certaines familles à des collections spécifiques (ex : « Diamètre » n'apparaît que dans « Visserie »)

### 7.bis.2 Pourquoi pas `Attribute` / `AttributeGroup` Lunar

Le système natif Lunar `Attribute` + `AttributeGroup` est **déclaratif uniquement** : il définit les champs d'un produit mais **stocke les valeurs dans la colonne JSON `lunar_products.attribute_data`**. Conséquence : aucun index MySQL exploitable pour `WHERE attribute_data->…`, donc filtrage multi-critères en O(n) sur 50k produits → inacceptable.

Les flags `filterable` / `searchable` de Lunar renvoient vers **Laravel Scout** (Meilisearch, Algolia, Typesense). Hors scope v1 : pas de service de recherche externe à opérer, pas de budget infra, pas de besoin de full-text FR sur les valeurs pour le moment.

### 7.bis.3 Solution — 4 tables MDE relationnelles indexées

Package `packages/mde/catalog-features/` — mono-composer PSR-4, Filament Plugin, aucune modif `vendor/`.

```
mde_feature_families
  id, handle (unique), name, position, multi_value (bool, default TRUE),
  searchable (bool), timestamps
  INDEX (position)

mde_feature_values
  id, feature_family_id (FK cascade), handle, name, position,
  meta (json nullable), timestamps
  UNIQUE (feature_family_id, handle)
  INDEX (feature_family_id, position)

mde_feature_value_product                        ← pivot produit ↔ valeur
  feature_value_id (FK cascade), product_id (FK lunar_products cascade)
  PRIMARY KEY (feature_value_id, product_id)     ← JOIN par valeur
  INDEX (product_id, feature_value_id)           ← JOIN par produit (reverse)

mde_feature_family_collection                    ← optionnel : restreint une famille à des collections
  feature_family_id (FK cascade), collection_id (FK lunar_collections cascade)
  PRIMARY KEY (feature_family_id, collection_id)
  INDEX (collection_id)
```

**Règles de design** :

- **PK composée** + index reverse sur le pivot produit → JOIN O(log n) dans les deux sens
- **Pas de colonne `id` auto** sur les pivots (inutile, perd la PK clustered MySQL)
- `multi_value = TRUE` par défaut — un produit peut porter plusieurs valeurs d'une même famille (ex : applications multiples). Les familles mono-valeur (marque) flippent le flag
- `mde_feature_family_collection` **vide** ⇒ famille globale (visible partout). Avec lignes ⇒ famille restreinte à ces collections. Pas d'héritage nestedset côté stockage — résolution à la query via `FeatureManager::familiesFor()`
- `meta` JSON laisse place à couleur hex, icône, bornes numériques sans migration

### 7.bis.4 Extension sans patch `vendor/`

- **Relation Product → FeatureValue** ajoutée via `Product::resolveRelationUsing('featureValues', …)` dans le ServiceProvider — aucune modif du model Lunar core
- **Onglet « Caractéristiques »** injecté sur `ProductResource` via `LunarPanel::extensions([ProductResource::class => [ProductFeaturesExtension::class]])` et un `ResourceExtension::getRelations()` qui ajoute un `RelationManager`
- **Resource top-level `FeatureFamilyResource`** enregistrée via un Filament Plugin (`CatalogFeaturesPlugin`), groupe **Catalogue**, table `->reorderable('position')` pour drag-n-drop natif

### 7.bis.5 API publique — façade `Features`

Singleton bindé par le provider, façade Laravel pour appel depuis n'importe où (jobs FAB-DIS, listeners, commandes artisan, autres modules MDE) :

```php
use Mde\CatalogFeatures\Facades\Features;

// Écriture
Features::attach($product, $value);
Features::detach($product, $value);
Features::sync($product, [$v1->id, $v2->id, $v3->id]); // remplace tout
Features::syncByHandles($product, [
    'marque'       => ['bosch'],
    'applications' => ['interieur', 'exterieur'],
]); // ne touche QUE les familles listées — préserve les autres attachements

// Lecture
Features::for($product);              // Collection groupée par famille
Features::familiesFor($product);      // familles applicables (globales + rattachées aux collections du produit)
Features::productsWith([1, 2, 3]);    // Builder Product filtré AND sur toutes les valeurs demandées
Features::countsFor($collection);     // [family_id => [value_id => count]] pour facettes
```

Chaque écriture dispatche un event Laravel :

- `FeatureValueAttached` — attach unitaire
- `FeatureValueDetached` — detach unitaire
- `ProductFeaturesSynced` — après `sync()` / `syncByHandles()`, payload `(product, attached, detached)`

**Un autre module peut s'abonner à ces events** sans toucher au package catalog-features (ex : invalider un cache Redis, relancer un indexeur, recompter des facettes, logguer).

### 7.bis.6 Hors scope v1

- **Import FAB-DIS** — module dédié, consommera `Features::syncByHandles()`. Aucun refactor attendu côté catalog-features
- **Mega-menu cache Redis versionné** — phase 3 front, observer `Collection::saved` + `ProductFeaturesSynced`
- **Rendu filtres facettes Blade/Livewire** — phase 3, consomme `Features::countsFor()`
- **Full-text FR sur valeurs** — flag `searchable` stocké mais non câblé (route vers Scout si besoin un jour)

---

## 7.ter Publiko Tree Manager — page admin unifiée catégories + caractéristiques

### 7.ter.1 Objectif

Une seule page admin (`/admin/tree-manager`) qui remplace l'aller-retour entre le CRUD Collections de Lunar Admin et la Resource `FeatureFamily` du package `catalog-features`. Mêmes écrans, mêmes données, même expérience drag-n-drop des deux côtés avec modales CRUD live et import/export JSON par arbre.

### 7.ter.2 Anatomie

- **Page Filament** : `app/Filament/Pages/TreeManager.php`, extend `Lunar\Admin\Support\Pages\BasePage`, enregistrée via `LunarPanel::panel()->pages([...])` dans `AppServiceProvider::register()`.
- **Livewire state public** : `$collectionGroupId`, `$collectionsTree` (nested array), `$featureFamilies` (flat array famille → values).
- **Côté catégories** : `Lunar\Models\Collection` + `NodeTrait` kalnoy/nestedset — arbo illimitée, reparenting par `saveAsRoot()` / `appendToNode()`, repositionnement exact via `insertBeforeNode()`.
- **Côté caractéristiques** : 2 niveaux stricts `FeatureFamily` → `FeatureValue`, position entière, renumérotation manuelle en transaction lors d'un drag (y compris renumbering de la famille source quand une valeur change de parent).
- **Modales CRUD** : Filament Actions (`createCollectionAction`, `editCollectionAction`, `deleteCollectionAction`, équivalents famille / valeur). Chaque action a son `->form()` et son `->fillForm()`. Déclenchées depuis la blade via `wire:click="mountAction('xxxAction', { id: 42 })"`.
- **Tab switcher** : 3 `Action` objects dans `getHeaderActions()` (catégories / caractéristiques / les deux) avec closures `->color()` dynamiques. Méthode `switchTab()` invalide le cache `$this->cachedHeaderActions` car Filament le fige au boot. Un `ActionGroup` dropdown ("...") regroupe les actions de maintenance (Réparer l'arbre).
- **Layout** : mode `both` = inline `style="grid-template-columns: repeat(2, minmax(0, 1fr))"` (pas Tailwind JIT, cf. section 13). Responsive via `@media (max-width: 1023px)` en CSS inline.
- **Drag-n-drop** : **SortableJS 1.15.2** chargé via **CDN** (`cdn.jsdelivr.net`) directement dans la blade — pas de dépendance npm, pas d'entry Vite admin. Alpine component `treeManager` inline attache Sortable sur chaque `<ul data-sortable="...">` et appelle `$wire.moveCollection / moveFeatureValue / moveFeatureFamily` au `onEnd`. Réinitialisation post-Livewire via `Livewire.hook('morph.updated')`. Les listes `collections` et `collection-children` partagent le groupe SortableJS `{ name: 'collections-tree', pull: true, put: true }` pour le reparenting cross-level. Même pattern `features-values` côté caractéristiques.
- **Import / export JSON** : boutons Export/Import dans le `headerEnd` slot de chaque `<x-filament::section>`, contextuels par arbre. Format versionné (`version: 1`). Import = transaction + `fixTree()` final côté collections, `updateOrCreate` par handle côté features (préserve l'ID existant).
  - **UX modales** : la modale Export affiche le JSON dans un `<textarea readonly>` avec un bouton « Copier » (clipboard) et « Télécharger JSON » (download fichier). La modale Import accepte un JSON collé dans un textarea **ou** un upload fichier (les deux options sont proposées). Côté helpers partagés : `clipboardJs()` fournit la copie Alpine via `alpineClickHandler()` ; `resolveImportPayload()` priorise le fichier uploadé, puis le textarea, ou abort 422 si vide.

### 7.ter.3 SEO catégories — choix de stockage

`meta_title` et `meta_description` sont stockés comme **Lunar Attributes translatables** dans `attribute_data` (type `Lunar\FieldTypes\TranslatedText`, section `seo`), **pas** en colonnes plates sur `lunar_collections`. Ils apparaissent donc aussi dans l'onglet « Attributs » de Lunar Admin standard, restent multi-langue, et n'imposent aucune migration structurelle sur une table Lunar.

Seeder : migration `2026_04_11_140000_add_mde_seo_collection_attributes.php` — crée (si absent) un AttributeGroup `collection_seo` et les 2 attributes via `Attribute::updateOrCreate` keyés sur `(attribute_type=collection, handle)`. Idempotent, rollback supprime les 2 handles.

### 7.ter.4 SortableJS via CDN — pourquoi pas npm

- Pas d'entry Vite admin dédié dans le projet (`resources/js/app.js` cible le front Blade)
- `FilamentAsset::register([Js::make(...)])` exigerait un build Vite pour résoudre l'URL, complexité inutile pour 1 page
- SortableJS n'a pas de dépendances, 45 Ko min, version épinglée en dur dans l'URL CDN → reproductible
- Chargement scopé à la page (balise inline dans `tree-manager.blade.php`), pas d'impact sur le reste de l'admin

Bascule npm envisageable si une 2ᵉ page admin a besoin de la même lib — créer alors `resources/js/admin.js` + `FilamentAsset::register()` dans `AppServiceProvider::boot()`.

### 7.ter.5 Hors scope

- Sélecteur `CollectionGroup` (la page utilise le premier groupe par ID — MDE n'en a qu'un en pratique)
- Image ou SEO sur `FeatureFamily` / `FeatureValue` (décision : caractéristiques restent purement fonctionnelles)
- Authorization fine : réutilise la policy Shield `page_TreeManager` générée automatiquement, rattachée au rôle `admin`. À régénérer via `make artisan CMD='shield:generate --panel=lunar'` après déploiement.

### 7.ter.6 Architecture performance (500+ nœuds)

Le TreeManager gère 500+ nœuds (catégories + familles + valeurs). Six décisions architecturales garantissent la fluidité :

**Livewire `#[Computed]` au lieu de `public`** — `collectionsTree` et `featureFamilies` sont des propriétés `#[Computed]`, pas `public`. Elles sont exclues du snapshot Livewire (sérialisé à chaque requête), réduisant le payload de ~80 KB à ~2 KB.

**`skipRender()` sur drag-drop** — Les 3 méthodes de déplacement (`moveCollection`, `moveFeatureFamily`, `moveFeatureValue`) appellent `$this->skipRender()` car SortableJS a déjà mis à jour le DOM côté client. Pas de re-rendu serveur nécessaire.

**CRUD dynamique via `unset()` + morph Livewire** — Les actions CRUD invalident le cache computed (`unset($this->collectionsTree)`) ce qui déclenche une requête fraîche et un morph DOM Livewire. Pas de rechargement de page.

**`withCount('products')` sur les collections** — Élimine les requêtes N+1 (504 COUNT individuels remplacés par une seule requête avec sous-SELECT COUNT).

**Recherche Alpine-only** — Le filtrage de recherche tourne entièrement côté client via Alpine.js (`x-on:input.debounce`), sans jamais déclencher de requête Livewire. Matching récursif : seuls les nœuds dont le label contient la query + leurs ancêtres structurels sont affichés. Le texte correspondant est surligné avec des éléments `<mark>`.

**SortableJS lazy init via WeakMap** — Les instances Sortable sont trackées dans un `WeakMap`. Après un morph Livewire, seuls les NOUVEAUX éléments `[data-sortable]` (absents du map) sont initialisés. Debounce via `requestAnimationFrame` pour battre les événements `morph.updated` multiples.

**Tous les nœuds dépliés par défaut** — Le CSS affiche les nœuds dépliés (`.tree-children` visible). Le repliage se fait par toggle de la classe `.tree-collapsed`. Pas de vérification `offsetParent`.

---

## 7.quater Taxonomie produit — Product Types / Attributs Lunar vs Caractéristiques MDE

### 7.quater.1 Deux systèmes complémentaires, pas concurrents

Le catalogue MDE repose sur **deux mécanismes orthogonaux** pour décrire un produit. L'un vient de Lunar core, l'autre de notre package MDE. Ne pas les confondre.

| | **Product Type + Attributes (Lunar)** | **Feature Families + Values (MDE)** |
|---|---|---|
| **Rôle** | Schéma déclaratif : quels champs un produit de ce type porte | Taxonomie filtrable : valeurs partagées entre produits pour facettes et mega-menu |
| **Stockage** | JSON `attribute_data` sur `lunar_products` (1 valeur par produit) | 4 tables relationnelles `mde_feature_*` avec pivot SQL indexé |
| **Exemples** | Poids (kg), dimensions (mm), puissance (W), description technique | Marque=Somfy, Applications=[résidentiel, copropriété], Matière=aluminium |
| **Indexable SQL** | Non — JSON non-indexable pour `WHERE` multi-critères | Oui — PK composée + index reverse, JOIN O(log n) |
| **Valeur unique par produit ?** | Oui (mesure, texte libre, nombre propre au produit) | Non — liste finie de valeurs réutilisées sur N produits |
| **Filtrage catalogue front** | Impossible sans Scout (Meili/Algolia) | Natif SQL via `Features::productsWith()` et `Features::countsFor()` |
| **Admin back-office** | Fiche produit Lunar Admin (onglet Attributs) | Publiko Tree Manager + onglet Caractéristiques injecté via `ProductFeaturesExtension` |

### 7.quater.2 Règle de décision

Pour chaque info produit à ajouter, poser ces questions dans l'ordre :

1. **La valeur est-elle unique par produit ?** (mesure précise, texte libre, dimension) → **Attribut Lunar** sur le Product Type
2. **Veut-on filtrer le catalogue dessus ?** (facette front, mega-menu, recherche par critère) → **Caractéristique MDE** (`FeatureFamily` + `FeatureValue`)
3. **La valeur est-elle réutilisée à l'identique sur plein de produits ?** (marque, norme, couleur catalogue) → **Caractéristique MDE**
4. **C'est du texte libre ou une mesure continue ?** → **Attribut Lunar**

Les deux coexistent sans conflit : un produit de type « Portail » peut porter simultanément les attributs `largeur_mm=3200`, `hauteur_mm=1800`, `poids_kg=85` (Lunar) **et** les caractéristiques `marque=Somfy`, `matière=aluminium`, `applications=[résidentiel, copropriété]` (MDE).

### 7.quater.3 Product Types Lunar — fonctionnement

- Table `lunar_product_types` (5 types seedés : Portail, Volet roulant, Motorisation, Clôture, Accessoire)
- Un Product Type déclare quels `Attribute` sont rattachés au produit (product-level) et à ses variantes (variant-level)
- Les Attributes sont des `Lunar\Models\Attribute` groupés par `AttributeGroup`, stockés comme `TranslatedText`, `Number`, `Text`, `Dropdown` dans `attribute_data`
- Admin : page « Types de produit » native Lunar Admin (menu Catalogue)
- **Pas encore câblé en prod** : 0 attributs assignés aux 5 types, à configurer quand les fiches produit seront enrichies (phase 2/3)

### 7.quater.4 Variantes Lunar — architecture

- `ProductOption` = axe de variation (Taille, Couleur, Finition)
- `ProductOptionValue` = valeur sur cet axe (S/M/L, Bleu/Rouge)
- `ProductVariant` = combinaison concrète (Bleu/M) → c'est le `Purchasable` qui porte SKU, prix (`lunar_prices`), stock
- Les variantes sont des combinaisons **finies et précalculées** — pas un outil de saisie libre

### 7.quater.5 Configurateur sur mesure (vision phase 3)

Pour les menuiseries sur mesure (le client saisit ses dimensions), les variantes Lunar ne conviennent pas (combinaisons infinies). Architecture envisagée :

- **`CartLine::meta`** — champ JSON cast array, prévu par Lunar pour stocker des données custom par ligne de panier (`{largeur_mm: 1247, hauteur_mm: 832, double_vitrage: true}`)
- **Cart Pipeline** (`CartLineModifier`) — modifier custom qui lit `$line->meta`, applique une formule `largeur × hauteur × tarif_m²`, override `unit_price`
- **Product Type Attributes** pour stocker les paramètres du configurateur (`tarif_m2`, `largeur_min_mm`, `largeur_max_mm`, `pas_mm`)
- **`CartValidator`** custom pour rejeter les `Cart::add()` si dimensions hors bornes
- Package cible : `packages/mde/product-configurator`
- **Pièges** : le Cart Fingerprinting Lunar doit inclure `meta` dans le hash (sinon manipulation post-checkout), et `OrderLine` doit recopier le `meta` au passage `Cart → Order` (vérifié : le pipeline standard le fait)

---

## 7.quinquies AI Importer — portage du module PrestaShop Publiko

### 7.quinquies.1 Contexte

Portage du module PrestaShop **Publiko AI Importer** (23 560 lignes de code, 43 actions, parsing Excel multi-feuilles, LLM, staging) vers un package Laravel `packages/mde/ai-importer/` intégré Lunar + Filament. Branche de travail : `ai-importer`. Plan détaillé : `docs/ai-importer-migration-plan.md`.

### 7.quinquies.2 Architecture

- Package **Filament Plugin** autonome `Mde\AiImporter\` sous `packages/mde/ai-importer/`
- 5 tables `mde_ai_importer_*` (configs, llm_configs, jobs, staging, logs)
- Pipeline d'actions **polymorphe** — 17 classes (après simplification Proposition D, fusion `multiply/divide/add/subtract` → `math`, `uppercase/lowercase/capitalize` → `change_case`, `map/category_map` → `map` multi-value, `prefix` supprimé au profit de `concat → truncate → change_case`)
- Workflow découpé en 2 Laravel Jobs queue (`ParseFileToStagingJob`, `ImportStagingToLunarJob`), replacent les 3 cron PS (`cron.php`, `cron-prepare.php`, `cron-import.php`)
- Preview & import via **Filament Resources** natives (pas de DataTables server-side custom) — réutilise les patterns perf TreeManager (`#[Computed]`, pagination SQL, cache Redis progress)

### 7.quinquies.3 Décisions tranchées

| Question | Choix | Raison |
|---|---|---|
| Simplification actions | 43 → 17 (Proposition D de `SIMPLIFICATION.md`) | Les actions PS étaient redondantes. Moins de code, UI cohérente |
| Stockage clé API LLM | Cast Eloquent `encrypted` | Le module PS stockait en clair → faille corrigée |
| Orchestration cron | Laravel Queues + `Bus::batch()` | Aligné stack, resume natif, pas de cron custom |
| Rollback | Backup ciblé tables Lunar concernées (JSON gzippé) plutôt que `spatie/laravel-backup` | Plus léger, plus rapide sur gros imports |
| Édition config JSON v1 | Textarea JSON brut + validation | Phase 5 livrera l'éditeur drag-n-drop Livewire/Alpine |
| UUID job | `HasUuids` Laravel + `uniqueIds()` | Remplace la génération manuelle PS `job_TIMESTAMP_random` |
| Virtual scroll preview | Non — Filament Table pagination SQL | 50 lignes/page suffit, même sur 10 000+ staging rows |
| Progress real-time | Cache Redis + Livewire polling 2s | Pas de Reverb en phase 1 (ajout possible phase 6) |

### 7.quinquies.4 Dépendance externe

- `phpoffice/phpspreadsheet: ^2.0 || ^3.0` — déclaré dans le composer du package. Phase 3 gérera l'itération streaming pour éviter le full-load en mémoire.

### 7.quinquies.5 Intégration avec le reste du projet

- **`mde/catalog-features`** : l'importeur appelle `Features::syncByHandles($product, [...])` à la fin de chaque row importée pour mapper les caractéristiques filtrables
- **Prix Lunar** : toujours écrits en **cents entiers** via `Price::updateOrCreate([...])`, jamais en float
- **`attribute_data`** : toujours assemblé comme collection de `Lunar\FieldTypes\*` (TranslatedText, Number…), jamais de strings bruts
- **Stock** : écrit sur `ProductVariant::stock` (int), pas de table dédiée — diffère du modèle PS `ps_stock_available`
- **Images** : Spatie MediaLibrary (`$product->addMediaFromUrl(...)`) — déjà utilisé par Lunar

### 7.quinquies.6 Navigation

Nouveau groupe Filament **« Imports »** (entre *Expédition* et *Configuration*) avec 3 Resources :

- `ImportJobResource` — liste des imports, création (upload + config + options), détail (preview staging + logs)
- `ImporterConfigResource` — CRUD configs de mapping par fournisseur
- `LlmConfigResource` — CRUD clés API LLM (Claude, OpenAI)

### 7.quinquies.7 Phase 1 — Foundation (commit 4a66a3e)

- Squelette package complet, 5 migrations appliquées, 5 modèles Eloquent, 6 enums, contrats, 16 classes d'action, LLM Manager + providers, 2 Jobs queue (squelettes), 3 Filament Resources, enregistrement `composer.json` + `bootstrap/providers.php` + `AppServiceProvider`.

### 7.quinquies.8 Phases 2-6 livrées (ce commit)

#### Phase 3 — Parsing multi-feuilles

- **`Services/SpreadsheetParser`** : lecteur PhpSpreadsheet (XLSX/XLS/CSV). Itération générateur sur la feuille primaire, pré-indexation paresseuse des feuilles secondaires par `join_key`. Chaque ligne est exposée en double clé : par nom d'en-tête ET par lettre de colonne (`A`, `B`, `M`...), pour rester rétro-compatible avec les configs PS qui pointent en lettres.
- **`Services/ProgressCache`** (Redis) : clé `ai-importer:job:{uuid}:progress` contenant `{processed, total, percentage, updated_at}`, TTL 15 min. Alimenté par les jobs toutes les `checkpoint_every` lignes, lu par Livewire polling (évite un SELECT DB toutes les 2 s).
- **`Jobs/ParseFileToStagingJob`** (réel, plus un stub) : lit le fichier, construit l'`ExecutionContext` par ligne (row + secondary sheets indexed), fait tourner `ActionPipeline` pour chaque colonne du mapping, bulk-insert `StagingRecord` avec `status=pending`. Resume natif via `last_processed_row`. Transition statut `pending → parsing → parsed/error` + écriture dans `ImportLog`.
- **Dépendance ajoutée** : `phpoffice/phpspreadsheet: ^3.0` au composer racine (PhpSpreadsheet 3.x — API stable, PHP 8.2+).

#### Phase 4 — Écriture Lunar + backup/rollback

- **`Services/LunarProductWriter`** : écriture d'un `StagingRecord` vers `Product` + `ProductVariant` + `Price` + `Collection` + `Brand` + MDE Features. Résolution par SKU (`reference`). Cache par instance pour brand et collection lookups. Contrat de clés staging documenté in-code — voir la docblock de la classe pour la liste exhaustive (17 clés reconnues).
- **`attribute_data`** assemblé comme Collection de `Lunar\FieldTypes\TranslatedText` pour `name`, `description`, `description_short`, `meta_title`, `meta_description`, `meta_keywords` + `Text` pour `url_key`. Préserve les traductions existantes sur update (merge par langue).
- **Prix** : `Price::updateOrCreate` keyé sur `(priceable_type, priceable_id, currency_id, customer_group_id=null, min_quantity=1)`, valeur en cents entiers. Support `compare_price_cents`.
- **Features MDE** : `Mde\CatalogFeatures\Facades\Features::syncByHandles()` via `class_exists` guard (le writer reste utilisable même si catalog-features est désinstallé).
- **`Services/LunarBackupManager`** : snapshot avant import. Collecte les SKUs présents en staging → récupère les `ProductVariant` correspondants + leurs produits + prix + pivot `lunar_collection_product`. Stockage `storage/app/ai-importer/backups/job_{uuid}_{datetime}.json.gz` (gzip level 6, typiquement 70-80% compression). Format JSON brut, lisible à l'œil. `restore()` replay en transaction.
- **`Jobs/ImportStagingToLunarJob`** (réel) : boucle `chunkById(200)` sur staging (statuts `pending|validated|warning`), appelle le writer par row, gère les 3 politiques d'erreur :
  - `ignore` → log + continue
  - `stop` → retourne false depuis `chunkById`, transition `import_status=error`
  - `rollback` → `LunarBackupManager::restore()` + `import_status=rolled_back`
- Snapshot créé UNE fois au premier run. Sur resume, le backup_path existant est réutilisé.

#### Phase 5 — UI Filament (RelationManagers + Actions)

- **`StagingRecordsRelationManager`** : tableau preview avec colonnes `row_number / SKU / nom / prix formaté / statut badgé / erreur`. Filtre par statut, search sur JSON (`where data like %"reference":"%Q%"`). Edit modale avec textarea JSON + validation `json`. Bulk actions : valider / ignorer / supprimer.
- **`ImportLogsRelationManager`** : tableau read-only, polling 5s, tri `id desc`, filtre par niveau, badge coloré. Fournit la vue temps-réel pendant le parse/import.
- **Header actions sur `ViewImportJob`** :
  - `Resume parse` — visible si `Paused | Error`
  - `Launch import Lunar` — visible si `Parsed` et `import_status ∈ {Pending, Scheduled}`
  - `Rollback` — visible si `Imported` avec `backup_path` et `!rollback_completed`
  - `Cancel` — visible si `Pending | Parsing | Paused`
- L'éditeur visuel drag-n-drop de config (remplace le textarea JSON actuel) reste hors scope — tracked comme phase 5+ dédiée.

#### Phase 6 — CLI migration depuis PS

- **`php artisan ai-importer:import-ps-config {file} [--name=] [--supplier=] [--replace]`** : lit un JSON Publiko AI Importer et crée un `ImporterConfig`. Compatibilité v0 : si une colonne a `action:{}` (objet unique legacy) au lieu de `actions:[]`, la commande le lift automatiquement. Affiche un résumé : colonnes mappées / actions totales / feuilles.

#### Phase 2 — Tests

- **`tests/Unit/AiImporter/Actions/ActionTypesTest`** (17 tests) : chaque type d'action (math, change_case, truncate, concat, template, map simple et multi-value, validate_ean13, slugify, replace/regex_replace, copy, trim, date_format, multiline_aggregate concat et count).
- **`tests/Unit/AiImporter/Services/ActionPipelineTest`** (5 tests) : chaînage ordonné, défaut sur valeur null, `condition` true/false, lève sur type inconnu.
- **`tests/Feature/AiImporter/ParseFileToStagingJobTest`** : CSV fake → pipeline → staging (fixture CSV minimale via `Storage::fake`).
- **`tests/Feature/AiImporter/LunarProductWriterTest`** (3 tests) : création produit + variant + prix, update sur 2e appel, erreur sur `reference` manquante.

Couverture totale : **26 nouveaux tests verts** (61 total sur le projet).

### 7.quinquies.9 Contrat du writer (clés staging reconnues)

Les clés suivantes, si présentes dans `StagingRecord::data`, déclenchent une écriture Lunar :

| Clé | Cible Lunar | Notes |
|---|---|---|
| `reference` **(requis)** | `ProductVariant::sku` | Résolveur : existe ? update. Sinon : create. |
| `name` **(requis en création)** | `attribute_data.name` (TranslatedText) | Stocké dans la langue par défaut |
| `description` | `attribute_data.description` (TranslatedText) | |
| `description_short` | `attribute_data.description_short` | |
| `meta_title` / `meta_description` / `meta_keywords` | `attribute_data.*` (TranslatedText) | |
| `url_key` | `attribute_data.url` (Text) | |
| `ean` | `ProductVariant::ean` | |
| `stock` | `ProductVariant::stock` (int) | |
| `price_cents` | `Price::price` (cents) | UpdateOrCreate keyé sur variant+currency+tier |
| `compare_price_cents` | `Price::compare_price` (cents) | Optionnel |
| `weight_value` | `ProductVariant::weight_value` (kg) | Unité forcée à `kg` |
| `length_value` / `width_value` / `height_value` | `ProductVariant::{axis}_value` (cm) | Unité forcée à `cm` |
| `brand_name` | `Product::brand_id` | `Brand::firstOrCreate(['name' => ...])` |
| `collections` | `Product::collections()` | Array ou CSV, int (ID) ou string (handle). `syncWithoutDetaching` |
| `features` | pivot `mde_feature_value_product` | Hash `{family_handle => [value_handle, ...]}`, delegated à `catalog-features` |

Les clés inconnues du writer sont **ignorées silencieusement** — le config author peut donc émettre des keys arbitraires pour d'autres consommateurs downstream.

### 7.quinquies.10 Limitations connues

- Images produits : Spatie MediaLibrary `addMediaFromUrl` pas câblé en phase 4 (à faire via une action `image_download` ou une clé `images[]` reconnue par le writer — à trancher).
- ProductType : le writer utilise le premier `ProductType` trouvé (ordre `id asc`). À remplacer par une résolution via clé `product_type_handle` sur le staging row.
- TaxClass : idem, premier trouvé.
- True streaming XLSX : `PhpSpreadsheet::load()` charge tout en RAM. Au-delà de ~100k lignes, basculer sur un `IReadFilter` chunked — l'API parser reste stable.
- Éditeur config visual : reste en textarea JSON (phase 5+).
- Appels LLM réels non testés automatiquement (nécessitent une clé API valide, hors CI).

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
- `Unit\CatalogFeatures\FeatureModelsTest` — ordre par position, cascade delete family→values, unicité handle par famille, scope `global()`
- `Feature\CatalogFeatures\FeatureManagerTest` — attach/detach/sync + events, `syncByHandles` préserve les familles non listées, `familiesFor()` mix globales + rattachées, `productsWith()` filtre AND

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
├── shipping-colissimo/                ← Mde\ShippingColissimo\
└── catalog-features/                  ← Mde\CatalogFeatures\ (familles + valeurs + pivot produit)

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

## 12. Outils IA — MCP servers projet

### 12.1 Décision

Le projet embarque **deux serveurs MCP (Model Context Protocol)** déclarés dans `.mcp.json` à la racine, accessibles par tout agent IA qui ouvre le dossier (Claude Code, Junie, etc.). Leur usage est **obligatoire** pour tout travail impliquant Laravel, Filament ou Lunar — voir `CLAUDE.md` §2 pour les règles comportementales.

### 12.2 Serveurs configurés

| Serveur | Package / Endpoint | Transport | Couverture |
|---|---|---|---|
| **`laravel-boost`** | `laravel/boost` v2.4 (dev) | stdio via `docker compose exec -T -u sail app php artisan boost:mcp` | Laravel 11, Filament 3, Livewire 3, PHP 8.3, Pint, Pest, Tailwind, schéma DB live, logs, tinker, routes, application-info |
| **`lunar-docs`** | `https://docs.lunarphp.com/mcp` | HTTP streamable | Doc officielle Lunar v1.x (`search_lunar_php`, `query_docs_filesystem`) |

### 12.3 Installation et configuration

**laravel-boost** :

```bash
make composer CMD='require laravel/boost --dev'
make artisan CMD='boost:install --mcp --no-interaction'
```

Le flag `--mcp` installe **uniquement** la config MCP dans `.mcp.json` — **pas** les guidelines (`--guidelines`) ni les skills (`--skills`), pour préserver le `CLAUDE.md` projet.

**Correction post-install** : `boost:install` génère une commande `vendor/bin/sail` qui ne s'applique pas à notre stack custom. Le `.mcp.json` est corrigé manuellement pour utiliser `docker compose exec -T -u sail app …`.

**lunar-docs** : déclaration HTTP directe dans `.mcp.json`, pas d'installation locale.

### 12.4 `.mcp.json` de référence

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "docker",
            "args": ["compose", "exec", "-T", "-u", "sail", "app", "php", "artisan", "boost:mcp"]
        },
        "lunar-docs": {
            "type": "http",
            "url": "https://docs.lunarphp.com/mcp"
        }
    }
}
```

### 12.5 Pourquoi pas Context7 uniquement

Context7 reste le fallback global (configuré au niveau utilisateur, pas projet), mais :

- Il n'est pas versionné par package installé — il peut servir la doc Lunar v2.x alors qu'on tourne en v1.x
- Il n'a pas accès au schéma DB local, aux logs, aux routes, au tinker
- Il ne résout pas les helpers projet

→ laravel-boost + lunar-docs sont **prioritaires**. Context7 est réservé aux packages tiers non couverts.

### 12.6 Fichier `boost.json`

Après un `boost:install` complet (avec guidelines), un `boost.json` est généré à la racine pour configurer quels packages Boost introspecte. Non utilisé ici puisqu'on n'installe que le flag `--mcp`.

---

## 13. Navigation admin — réorganisation et Media Library

### Problème initial

L'admin Lunar enregistre ~20 resources dans 3 groupes anglais (`Catalog`, `Sales`, `Settings`). Le projet définissait des groupes français (`Catalogue`, `Commandes`, `Configuration`) dans `AppServiceProvider`, mais sans traductions FR pour Lunar → les resources Lunar se retrouvaient dans des groupes anglais distincts des groupes français de l'app.

### Solution : traductions FR Lunar + sous-classes navigation + reflection swap

**Traductions** : `lang/vendor/lunarpanel/fr/global.php` mappe `catalog→Catalogue`, `sales→Commandes`, `settings→Configuration`. Les resources Lunar tombent maintenant dans les bons groupes français.

**Sous-classes** : 4 resources dans `app/Filament/Resources/Mde*Resource.php` étendent les resources Lunar `ProductTypeResource`, `ProductOptionResource`, `AttributeGroupResource`, `CollectionGroupResource` pour changer uniquement `getNavigationGroup()` → `'Paramètres catalogue'`. `ProductTypeResource` retire aussi `getNavigationParentItem()` (était imbriqué sous "Produits").

**Reflection swap** : `LunarPanelManager::$resources` est `protected static` sans setter. `AppServiceProvider::swapLunarResources()` utilise `ReflectionProperty` pour substituer les 4 classes **avant** `register()`. Fragile en cas de changement interne Lunar — à surveiller lors des mises à jour.

### Navigation cible

| Groupe | Entrées | Usage |
|--------|---------|-------|
| **Catalogue** | Produits, Medias, Marques, Catégories, Caractéristiques | Quotidien |
| **Paramètres catalogue** (collapsed) | Types de produits, Options de produits, Groupes d'attributs, Groupes de collections | Setup initial |

**TreeManager** : anciennement 1 entrée nav, désormais 2 (`Catégories` et `Caractéristiques`) via `getNavigationItems()` retournant 2 `NavigationItem` avec query param `?tab=categories|features`. Toggle 3 modes sur la page (catégories seules, features seules, les deux).

**UX header actions** : le tab switcher (catégories / caractéristiques / les deux) est implémenté comme des `Action` objects dans `getHeaderActions()` avec des closures dynamiques `->color(fn (): string => $this->activeTab === 'xxx' ? 'primary' : 'gray')`. Raison : les boutons vivent dans la barre d'en-tête Filament natif, pas dans un composant Blade custom. Le bouton « Réparer l'arbre » et les actions de maintenance sont regroupés dans un `ActionGroup` dropdown (icône "...") pour désencombrer la barre.

**Export/Import** : les boutons Export/Import sont placés dans le `headerEnd` slot de chaque `<x-filament::section>` (catégories et caractéristiques), pas dans les header actions globaux. Chaque section a ses propres boutons contextuels.

**`switchTab()` et cache Filament** : Filament cache les header actions pendant `bootedInteractsWithHeaderActions()`. Un simple `$set` ou `$this->activeTab = ...` ne suffit pas à rafraîchir les couleurs des boutons tab. La méthode `switchTab()` vide manuellement `$this->cachedHeaderActions = []` puis rappelle `$this->cacheHeaderActions()` pour forcer la réévaluation des closures `color()`.

**Layout côte-à-côte** : le mode `both` utilise un `style="grid-template-columns: repeat(2, minmax(0, 1fr))"` inline au lieu d'une classe Tailwind (`grid-cols-2`). Raison : Tailwind JIT ne résout pas les classes dynamiques générées par Livewire morph (`@if($activeTab === 'both') class="grid-cols-2" @endif` n'est pas scanné par le compilateur JIT). Le style inline + media query CSS `@media (max-width: 1023px)` gère le responsive.

**SortableJS cross-level drag** : les listes catégories racine (`data-sortable="collections"`) et enfants (`data-sortable="collection-children"`) partagent le même groupe SortableJS `{ name: 'collections-tree', pull: true, put: true }`. Cela permet le reparenting drag-and-drop entre niveaux (racine ↔ enfant, enfant ↔ autre parent). Même pattern côté valeurs de caractéristiques avec le groupe `features-values`.

**FeatureFamilyResource** : `shouldRegisterNavigation()` retourne `false`. Resource toujours enregistrée (URLs actives), juste absente du sidebar.

### Media Library — `tomatophp/filament-media-manager`

Package retenu pour la gestion centralisée des médias (photos, vidéos) dans l'admin, style WordPress. Basé sur Spatie MediaLibrary (compatible Lunar qui l'utilise déjà).

- Dossiers et sous-dossiers
- Alt tags / titres / descriptions via custom properties Spatie
- Traductions FR : `lang/vendor/filament-media-manager/fr/messages.php`
- Config : `config/filament-media-manager.php`, `navigation_sort => 2`
- Tables : `folders`, `media_has_models`, `folder_has_models`

Packages écartés : `awcodes/filament-curator` (incompatible Spatie), `outerweb/filament-image-library` (système propre incompatible). Option premium `ralphjsmit/media-library-pro` non retenue pour le moment (budget).

### Médiathèque custom — `MdeMediaLibrary` (route `admin/mediatheque`)

La page Filament par défaut de `tomatophp/filament-media-manager` est conservée en backend (modèle `Folder`, tables) mais **remplacée par une page custom** `Mde\StorefrontCms\Filament\Pages\MdeMediaLibrary` pour l'UI (layout WP-style, multi-sélection, lightbox, slide-over édition).

**Uploader optimiste WP-style** :
- Dropzone HTML custom (pas de FilePond) : `<label>` + `<input type="file" multiple>` + drag/drop natif
- Tuiles "en cours" injectées dans la grid via Alpine (`x-data="mlibUploader()"`) avec preview locale (`URL.createObjectURL`) et spinner SVG circulaire basé sur l'event `progress` de Livewire
- Persistance : `$wire.upload('pendingUpload', file, finishCb, errorCb, progressCb)` → property `$pendingUpload` (trait `Livewire\WithFileUploads`) → méthode `persistPendingUpload(string $originalName)` qui fait `$folder->addMedia()->toMediaCollection()`
- Sécurité suppression dossier : `deleteFolder()` compte les médias (`model_type=Folder, model_id=$id`) et refuse si > 0
- Multi-sélection : property `$selectedMediaIds[]`, actions `bulkDeleteMedias()` / `confirmBulkMove()` (update `model_id` + `collection_name`, fichiers physiques inchangés car le path Spatie est basé sur l'id)

### Thème Filament custom — `resources/css/filament/admin/`

Le panel Lunar ne charge pas le CSS storefront (`resources/css/app.css`). Les classes Tailwind utilisées dans les views custom Filament (pages, plugins MDE) ne sont donc pas compilées dans le bundle admin par défaut.

**Solution** : thème Filament dédié, enregistré via `->viteTheme('resources/css/filament/admin/theme.css')` dans `AppServiceProvider`.

**Structure atomique** (une règle : un module = un fichier) :
```
resources/css/filament/admin/
├── theme.css                      # entrée — imports vendor Filament + modules
├── tailwind.config.js             # preset Filament + scan des views admin et packages MDE
└── modules/
    └── media-library.css          # styles de MdeMediaLibrary
```

- Pour modifier un module : éditer **uniquement** son fichier dans `modules/`
- Pour ajouter un module : créer `modules/<nom>.css` + ajouter `@import './modules/<nom>.css';` en tête de `theme.css` (contrainte CSS : tous les `@import` doivent précéder tout autre contenu)
- Ajouté à `vite.config.js` en 2ᵉ input aux côtés de `resources/css/app.css`

**Dépendances NPM ajoutées** (requises par le preset Filament) :
- `@tailwindcss/typography` (dev)
- `postcss-nesting` (dev)

---

## 13.bis Module Fidélité — package `mde/loyalty`

Portage du module PrestaShop `publikoloyalty` (v1.1.0) vers Lunar. Phase 1 : back-office uniquement, storefront différé.

### Décisions
- **Package** : `packages/mde/loyalty`, namespace `Mde\Loyalty\`, ServiceProvider `LoyaltyServiceProvider` (enregistré dans `bootstrap/providers.php`).
- **Plugin Filament** : `LoyaltyPlugin` enregistré dans `AppServiceProvider` après `CatalogFeaturesPlugin`. Resources : `LoyaltyTier` (CRUD), `GiftHistory` (statut + notes + badge nav unviewed), `PointsHistory` (readonly). Page `LoyaltySettings` (ratio + email admin). Group nav : **Marketing**.
- **Trigger calcul points** : observer Eloquent sur `Lunar\Models\Order` (`updated`/`created`), déclenché quand `placed_at` passe à non-null. Choix vs `PaymentAttemptEvent` : robuste pour les paiements offline et idempotent (vérification d'existence dans `mde_loyalty_points_history.order_id` unique).
- **Source HT** : colonne `lunar_orders.sub_total` (entier cents, hors taxes). `points = floor((sub_total/100) / ratio)`.
- **Anti-doublon palier** : index unique `(customer_id, tier_id)` sur `mde_loyalty_gift_history`.
- **Notifications** : `Illuminate\Notifications\Notification` (mail). Client via routing sur `Customer->users()->first()->email`. Admin via `Setting::get('admin_email')` puis fallback `config('mde-loyalty.admin_email')` / env `MDE_LOYALTY_ADMIN_EMAIL`.
- **Settings** : table dédiée `mde_loyalty_settings(key, value)` — pas de dépendance `spatie/laravel-settings` ajoutée. Lecture via `Mde\Loyalty\Models\Setting::get()`.

### Tables (préfixe `mde_loyalty_`)
- `mde_loyalty_tiers` — paliers (name, points_required, gift_*, position, active)
- `mde_loyalty_customer_points` — agrégat par client (unique customer_id)
- `mde_loyalty_points_history` — trace par commande (unique order_id → idempotence)
- `mde_loyalty_gift_history` — déblocages (status enum pending/processing/sent, admin_notes, admin_viewed)
- `mde_loyalty_settings` — kv config

### Variables d'env
- `MDE_LOYALTY_DEFAULT_RATIO` (défaut `1` — 1€HT = 1 point)
- `MDE_LOYALTY_ADMIN_EMAIL` — destinataire des notifications de déblocage côté admin

### Backlog phase 2
- Storefront sections (progress / next gifts / unlocked / history) — data déjà exposée via `LoyaltyManager::getCustomerSnapshot()`.
- Gestion remboursements / annulations (retrait points).
- Commande artisan `loyalty:recalculate` pour rejouer historique clients existants.

---

## 13.bis Storefront Livewire (phase 1)

Port du [Lunar Livewire Starter Kit](https://github.com/lunarphp/livewire-starter-kit) comme base du frontoffice public. Le starter kit est un **template d'application Laravel**, pas un package Composer : les fichiers ont été copiés manuellement dans l'app existante.

### Fichiers portés (1:1 depuis le starter kit)
- `app/Livewire/{Home,CheckoutPage,CheckoutSuccessPage,CollectionPage,ProductPage,SearchPage}.php`
- `app/Livewire/Components/{AddToCart,Cart,CheckoutAddress,Navigation,ShippingOptions}.php`
- `app/Traits/FetchesUrls.php`
- `app/View/Components/ProductPrice.php`
- `resources/views/{layouts,livewire,components,partials}/**`
- Assets : `resources/css/app.css` (no-spinner utilities + `[x-cloak]`), `resources/js/app.js`, `tailwind.config.js` (plugin `@tailwindcss/forms` + content path `vendor/lunarphp/stripe-payments/resources/views`)
- `package.json` : ajout `@tailwindcss/forms`, `@ryangjchandler/alpine-clipboard`
- `config/livewire.php` publié, `layout => 'layouts.storefront'`

Tous les fichiers PHP portés portent `declare(strict_types=1);` (CLAUDE.md §3.2).

### Routes publiques

| Méthode | URI | Component | Nom |
|---|---|---|---|
| GET | `/` | `Home` | `home` |
| GET | `/search` | `SearchPage` | `search.view` |
| GET | `/collections/{slug}` | `CollectionPage` | `collection.view` |
| GET | `/products/{slug}` | `ProductPage` | `product.view` |
| GET | `/checkout` | `CheckoutPage` | `checkout.view` |
| GET | `/checkout/success` | `CheckoutSuccessPage` | `checkout-success.view` |

### Écarts volontaires vs. starter kit

- **Non porté : `app/Providers/AppServiceProvider.php`** — celui du projet gère déjà LunarPanel (avec Shield + ResourceExtensions MDE) ; pas d'override de `Lunar\Models\Product` via `ModelManifest::replace()`.
- **Non porté : `app/Modifiers/ShippingModifier.php`** — l'option « Basic Delivery » factice du starter kit n'a pas lieu d'être : Table Rate Shipping + drivers Chronopost/Colissimo (voir §5) fournissent les options réelles.
- **Non porté : `app/Models/Product.php` / `CustomProduct.php`** — on passe par `Lunar\Models\Product` natif + mécanismes d'extension documentés (§3).
- **Non porté : dépendances `laravel/sanctum`, `meilisearch/meilisearch-php`, `predis/predis`, `league/flysystem-aws-s3-v3`** — pas d'API storefront en phase 1 ; Redis via `phpredis` ; Scout déjà installé ; pas de S3.
- **Non porté : seeders de démo (`ProductSeeder`, `OrderSeeder`, `CollectionSeeder`, `CustomerSeeder`…)** — l'import AI (§11) et les données réelles couvrent le besoin.
- **Non porté : configs `config/lunar/*` du starter kit** — les configs MDE sont déjà publiées et tunées (Stripe, shipping, panel, etc.).

### Dépendances NPM ajoutées
- `@tailwindcss/forms` ^0.5.9
- `@ryangjchandler/alpine-clipboard` ^2.3.0

### Limitations connues / follow-up

- **Pays pour le checkout** : `CheckoutPage::getCountriesProperty()` retourne uniquement `['GBR', 'USA']` (hérité starter kit). À remplacer par la France/UE pour MDE.
- **Recherche** : `SearchPage` repose sur `Product::search()` (Scout). Nécessite un driver configuré (Algolia/Typesense/DB) ; sinon les résultats seront vides.
- **Navigation** : `Navigation::getCollectionsProperty()` charge toutes les collections en arbre à chaque requête — à mettre en cache si le catalogue explose.
- **Stripe JS** : `layouts/checkout.blade.php` inclut l'initialisation Stripe ; `STRIPE_PK` doit être exposé côté vue.
- **UI** : starter kit basique non-production ready, sera amené à être refondu (Inertia+Vue kit à surveiller).

### Impact back-office
- Aucun. `/admin` (Filament + Shield) inchangé, routes et middlewares séparés.

## 15. Storefront B2B pro (phase 2)

Transformation complète du storefront (starter kit Livewire basique porté en §13.bis) en **frontoffice B2B pro-only** inspiré de Foussier. Catalogue navigable en libre accès, **prix masqués pour les visiteurs** (CTA "Connectez-vous pour voir vos prix"), achat/panier/compte réservés aux comptes pros vérifiés SIRET.

### 15.1 Décisions arbitrées

| Sujet | Choix | Pourquoi |
|---|---|---|
| Visibilité | Strict pro (Foussier-like) | Aligné B2B, SEO préservé sur catalogue + fiches produits |
| Inscription | Auto + vérif SIRET API INSEE V3 | DX pro instantanée, fallback validation manuelle si API indispo |
| Mono-user | 1 User ↔ 1 Customer | Simple phase 1, multi-user refactor si besoin ultérieur |
| Recherche | Scout driver `database` (DB LIKE) | Zero dépendance tierce ; Typesense documenté en follow-up |
| Charte | Palette bleu pro B2B (Tailwind primary=blue, neutral=slate) | Placeholder, remplaçable via 1 token quand logo/hex fournis |
| CMS | Tables simples + Livewire, Filament admin en follow-up | Time-to-market, UI admin ajoutable sans migration |

### 15.2 Architecture en 7 packages MDE

| Package | Rôle |
|---|---|
| `packages/mde/storefront` | Design system (tokens Tailwind, Blade UI `<x-ui.*>`), layout `<x-layout.storefront>`, header Foussier-like (top contact bar + main bar + mega-menu collections cache 1h + info banner) + footer 4 colonnes CMS + USPs, `SearchAutocomplete` Livewire |
| `packages/mde/customer-auth` | `SireneClient` (INSEE V3 + token cache 6h + fallback pending), `RegisterProCustomer` action, Livewire pages Login/Register/Forgot/Reset + layout auth dédié, middlewares `pro.customer` + `redirect.if.pro` |
| `packages/mde/account` | Layout sidebar `/compte`, 8 pages Livewire (dashboard, profil, société, adresses, commandes, commande-détail, fidélité, factures), `AccountContext` helper |
| `packages/mde/purchase-lists` | Tables `mde_purchase_lists` + `mde_purchase_list_items`, models, 3 Livewire (index, détail, picker modal) |
| `packages/mde/quick-order` | `QuickOrderPage` Livewire (table dynamique + coller-Excel), `SkuResolver` service |
| `packages/mde/storefront-cms` | Tables `mde_home_slides`, `mde_home_tiles`, `mde_home_offers`, `mde_posts`, `mde_pages`, `mde_newsletter_subscribers` ; 5 Livewire blocks home + 3 controllers (posts, pages, newsletter) |
| `packages/mde/store-locator` | Table `mde_stores`, routes `/magasins` + `/magasins/{slug}` avec carte Leaflet CDN + OpenStreetMap |

### 15.3 Design system — `<x-ui.*>` et `<x-layout.*>`

Tokens Tailwind (`tailwind.config.js`) :
```js
colors: { primary: blue, neutral: slate, success: emerald, warning: amber, danger: rose }
fontFamily: { sans: ['Inter', ...] }  // CDN Google Fonts
```

Composants UI : `button`, `input`, `select`, `textarea`, `checkbox`, `card`, `badge`, `alert`, `breadcrumb`, `dropdown` + `dropdown-item`, `modal`, `icon` (26 SVG inline, pas de Heroicons dep).

Composants layout : `header`, `footer`, `search-bar`, `logo` (SVG placeholder), `usps`, `storefront` (wrapper layout complet).

Composants storefront : `product-card` (Foussier-like avec marque + code + N variantes + price-gate + boutons Liste/Cart), `price-gate` (auth-aware wrapper), `add-to-cart` (gated + redirige vers product page si multi-variante).

### 15.4 Price gating

`<x-storefront.price-gate :product :variant size="md">` :
- Si `auth()->user()` + `Customer::sirene_status='active'` + `CustomerGroup::handle='installateurs'` → `Pricing::for($variant)->get()->matched->price->formatted()`.
- Sinon → `<x-ui.button href="/connexion" icon="user">Connectez-vous pour voir vos prix</x-ui.button>`.

Même logique sur `<x-storefront.add-to-cart>`. Routes gated par middleware `pro.customer` : `/panier`, `/checkout*`, `/compte*`, `/achat-rapide`, `/compte/listes-achat*`.

### 15.5 Inscription pro + vérification SIRET

`Mde\CustomerAuth\Sirene\SireneClient` :
- `validateSiret(string): bool` — Luhn + 14 digits (statique).
- `verify(string): SireneResult` — appelle `/siret/{siret}` API INSEE V3 avec Bearer OAuth2 client_credentials (token cache Redis 6h).
- Retourne `Status::Active` (établissement actif), `Status::Inactive` (404 ou `etatAdministratifEtablissement ≠ A`), `Status::Pending` (API disabled, timeout, 5xx).

`RegisterProCustomer::handle($dto)` :
- Transaction : crée `Lunar\Models\Customer` (raison, TVA FR depuis clé, meta.siret/naf/adresse INSEE), attache group `installateurs`, crée `User` lié via pivot `customer_user`. Retourne `['user', 'customer', 'sirene']`.
- Si `Status::Inactive` → `DomainException` bloquante.
- Si `Status::Pending` → compte créé mais `sirene_status='pending'` → middleware refuse tant que non promu active.

Migration `2026_04_17_120000_add_sirene_columns_to_lunar_customers` : `sirene_status` (indexed), `sirene_verified_at`, `naf_code`.

Env requis pour INSEE : `INSEE_ENABLED=true`, `INSEE_API_KEY`, `INSEE_API_SECRET` (par défaut `INSEE_ENABLED=false` → fallback pending, admin valide manuellement).

### 15.6 Routes publiques + gated

```
Public :
  /  (home Livewire refondue)
  /collections/{slug}  (faceted filters via catalog-features)
  /produits/{slug}
  /recherche  (DB LIKE, multi-champs)
  /magasins  /magasins/{slug}  (Leaflet CDN)
  /actualites  /actualites/{slug}
  /pages/{slug}
  POST /newsletter
  /connexion  /inscription  /mot-de-passe-oublie  /reinitialisation/{token}

Pro gated (middleware pro.customer = auth + CustomerGroup installateurs + sirene_status active) :
  /panier  /checkout  /checkout/success
  /achat-rapide
  /compte  /compte/profil  /compte/societe  /compte/adresses
  /compte/commandes  /compte/commandes/{order}
  /compte/listes-achat  /compte/listes-achat/{list}
  /compte/fidelite  /compte/factures
  POST /deconnexion

Admin :
  /admin  (Filament, Shield, intact)
```

### 15.7 Catalogue faceté (CollectionPage)

`CollectionPage` refondu :
- `#[Url(as: 'f')]` pour préservation des filtres, `#[Url(as: 'sort')]` pour tri, `WithPagination`.
- Sidebar filtres via `Mde\CatalogFeatures\Facades\Features::countsFor($collection)` + checkboxes.
- `Features::productsWith($selectedValueIds)` pour filter Product IDs, JOIN avec `whereHas('collections')`.
- Tri : nouveautés (default), prix asc/desc, nom A-Z.
- Pagination 24 items/page, grid 3 cols desktop.

### 15.8 Homepage refondue

`resources/views/livewire/home.blade.php` (utilisé par `App\Livewire\Home`) :
- `<livewire:storefront-cms.home-hero>` — carrousel slides Alpine auto-play 6s + dots + prev/next (cache 15min)
- `<livewire:storefront-cms.home-tiles>` — 4 tuiles portails/volets/automatismes/motorisations
- `<livewire:storefront-cms.home-featured>` — 6 produits collection `config('mde-storefront.home.featured_collection_slug')` ou fallback latest
- `<livewire:storefront-cms.home-offers>` — 4 offres du moment
- Pitch SEO-friendly MDE (paragraphe keyword-riche)
- `<livewire:storefront-cms.home-posts>` — 4 dernières actus publiées

Toutes les requêtes home cachées 15min (clé versionnée à ajouter via observer sur events Post/Slide/Tile/Offer `saved` dans un follow-up).

### 15.9 Nouvelles dépendances

- Aucun ajout Composer (tout construit sur l'existant Laravel 11 + Lunar + Livewire + Spatie Permission déjà présents).
- CDN Leaflet 1.9.4 (`/magasins`, `/magasins/{slug}`) — chargé `@push('head')` / `@push('scripts')` local à ces pages.
- CDN Google Fonts Inter (layout principal).

### 15.10 Variables d'environnement ajoutées

```env
# Inscription pro INSEE (désactivé par défaut → fallback validation manuelle)
INSEE_ENABLED=false
INSEE_BASE_URL=https://api.insee.fr/entreprises/sirene/V3.11
INSEE_API_KEY=
INSEE_API_SECRET=
INSEE_TIMEOUT=5

# Contact front (config/mde-storefront.php)
MDE_CONTACT_PHONE="02 XX XX XX XX"
MDE_CONTACT_EMAIL=contact@mde-distribution.fr
MDE_CONTACT_TAGLINE="Besoin d'un conseil ?"

# Réseaux sociaux (optionnel)
MDE_SOCIAL_FACEBOOK=
MDE_SOCIAL_INSTAGRAM=
MDE_SOCIAL_LINKEDIN=
MDE_SOCIAL_YOUTUBE=

# Bannière info + shipping
MDE_BANNER_ENABLED=true
MDE_BANNER_TEXT="Livraison offerte dès 125 € HT"
MDE_MIN_FREE_SHIPPING_CENTS=12500

# Home (optionnel)
MDE_HOME_FEATURED_COLLECTION=
```

### 15.11 Nouveaux tests de référence

Compte de test pro (seeded par `MdeCustomerSeeder`) :
- Email : `thierry.leroy@mde-distribution.test`
- Password : `testing123`
- Customer `Leroy Fermetures`, SIRET `12345678900015`, group `installateurs`, `sirene_status=active`

Compte admin Filament (seeded par `MdeAdminUserSeeder`) :
- Email : `admin@mde-distribution.fr`
- Password : `testing123`

### 15.12 Administration CMS (Filament)

Nouveau groupe de navigation **Storefront** dans l'admin Filament avec 7 resources et 1 page :

| Resource / Page | Modèle | Fonctionnalités |
|---|---|---|
| **Slides accueil** | `HomeSlide` | CRUD modal, reorder drag-n-drop (`position`), color pickers fond/texte, dates début/fin, CTA. Cache `mde.home.slides.v1` flush au save. |
| **Tuiles accueil** | `HomeTile` | 4 cards promotionnelles (titre, sous-titre, image, CTA, reorder). Cache `mde.home.tiles.v1`. |
| **Offres du moment** | `HomeOffer` | Badge (ex. -25%), image, date fin, CTA. Cache `mde.home.offers.v1`. |
| **Actualités** | `Post` | RichEditor Filament, slug auto depuis titre, cover, extrait, status draft/published, date publication. Cache `mde.home.posts.v1`. |
| **Pages CMS** | `Page` | RichEditor, slug unique, status (published/draft). Routes `/pages/{slug}` (CGV, mentions, FAQ, politique…). |
| **Abonnés newsletter** | `NewsletterSubscriber` | Liste read-only (pas de create), bulk delete, search + sort. |
| **Magasins** | `Store` | Sections Identité / Adresse / Contact / Horaires (`KeyValue` jour→plage), slug auto, coordonnées lat/lng. |
| **Paramètres** (page) | `Setting` | Contact (tél, e-mail, accroche), bannière info (toggle + texte + icône select), seuil livraison offerte cents, social links (FB/IG/LI/YT), USPs via `Repeater` icône+titre+sous-titre, slug collection vedette home. |

Plugins Filament : `Mde\StorefrontCms\Filament\StorefrontCmsPlugin` et `Mde\StoreLocator\Filament\StoreLocatorPlugin` enregistrés dans `AppServiceProvider`. Nav group inséré avant `Commandes`.

**Source de vérité config** : table `mde_storefront_settings` (key/value JSON) → modèle `Mde\StorefrontCms\Models\Setting` (helper static `get/set/forget`, cache Redis 1h). `StorefrontCmsServiceProvider::mergeDbSettingsIntoConfig()` au boot surcharge les valeurs de `config('mde-storefront.*')` quand la table est peuplée (guard `Schema::hasTable` pour install/CI). Résultat : l'admin peut éditer tous les réglages frontoffice sans toucher au `.env`.

Policies Shield régénérées (`make artisan CMD='shield:generate --all --panel=admin --no-interaction'`) après ajout des nouveaux modèles.

### 15.13 Follow-up documentés (hors scope phase 2)

- **Scout Typesense** : upgrade du driver `database` → Typesense self-hosted pour performance + typo-tolerance sur 60k+ références.
- **Pays checkout** : `CheckoutPage::getCountriesProperty()` hardcodé `[GBR, USA]` (hérité starter kit) à remplacer par France/UE.
- **Cache Navigation versionné** : observer `Collection::saved` qui invalide `mde.storefront.nav.roots.v1`.
- **Factures PDF** : page `/compte/factures` = placeholder. Génération via Spatie Browsershot ou équivalent.
- **Loyalty UI storefront** : `/compte/fidelite` est branché sur `LoyaltyManager::getCustomerSnapshot()` mais le rendu barre/cadeaux est minimal — design à finaliser.
- **Adresses CRUD** : `/compte/adresses` liste les adresses mais le bouton Ajouter est désactivé (follow-up Livewire create/edit).
- **Multi-user société** : 1:1 User↔Customer phase 1. Upgrade = pivot roles + invitation flow.
- **Reviews** : `/home` prévoyait un bloc reviews (non inclus — intégration Avis Vérifiés en follow-up).
- **SEO** : sitemap XML, schema.org Product/Organization/Store, meta tags dynamiques (scope phase suivante).

## 13.ter Système de médias unifié — `mde_mediables`

### Objectif

Permettre à **n'importe quelle entité** (CMS, Lunar, futurs modules MDE) d'être liée à un ou plusieurs médias de la médiathèque, façon WordPress : image unique (featured) ou galerie ordonnée. Remplace totalement l'usage natif Lunar/Spatie côté admin, **sans toucher `vendor/`**.

### Table pivot polymorphique

`database/migrations/2026_04_17_100000_create_mde_mediables_table.php` :

| Colonne | Type | Rôle |
|---|---|---|
| `media_id` | FK → `media.id` (cascade) | Référence Spatie Media |
| `mediable_type` / `mediable_id` | morph polymorphique | L'entité cible |
| `mediagroup` | string, default `'default'` | Groupe logique : `'cover'`, `'gallery'`, `'thumbnail'`, `'hero'`… |
| `position` | unsignedInt, default 0 | Ordre dans une galerie |

UNIQUE(`media_id`, `mediable_type`, `mediable_id`, `mediagroup`) — pas de doublon dans un même groupe. INDEX sur le triplet morph + mediagroup + position.

### Trait `HasMediaAttachments`

`packages/mde/storefront-cms/src/Concerns/HasMediaAttachments.php` — à `use` sur n'importe quel modèle. Méthodes clé :

- `mediaAttachments(?string $group = null): MorphToMany` — relation ordonnée par position, optionnellement filtrée par groupe.
- `firstMedia(string $group = 'default'): ?Media`
- `firstMediaUrl(string $group = 'default', string $conversion = ''): ?string`
- `syncMediaAttachments(array $ids, string $group)` — détache + réattache avec positions 0..N.
- `attachMedia(int $id, string $group, ?int $position = null)` / `detachMedia(int $id, ?string $group = null)`.

Pivot Eloquent : `Mde\StorefrontCms\Models\Mediable` (MorphPivot sur `mde_mediables`).

### Champ Filament `MediaPicker`

`packages/mde/storefront-cms/src/Filament/Forms/Components/MediaPicker.php` + vue `resources/views/forms/components/media-picker.blade.php`.

API :
```php
MediaPicker::make('cover')->mediagroup('cover');                // single
MediaPicker::make('gallery')->multiple()->mediagroup('gallery'); // multi
```

- Vide : bouton « + Choisir une image ».
- Rempli : miniature (single) ou grille (multi) avec boutons Retirer / Ajouter / Remplacer.
- Clic → `Livewire.dispatch('open-media-picker-modal', { statePath, multiple, preselected, mediagroup })`.
- `dehydrated(false)` : l'état n'est pas écrit sur le modèle. La persistance passe par `saveRelationshipsUsing()` qui appelle `syncMediaAttachments()` après save du record parent.

### Modal globale `MediaPickerModal`

Composant Livewire `Mde\StorefrontCms\Livewire\MediaPickerModal`, enregistré :

- sous l'alias `mde-media-picker-modal` dans `StorefrontCmsServiceProvider`
- rendu une seule fois par panel Filament via `->renderHook('panels::body.end', ...)` dans `StorefrontCmsPlugin::register()`

Fonctionnalités : sidebar dossiers (réutilise `TomatoPHP\FilamentMediaManager\Models\Folder`), grille médias avec case à cocher (single = un seul, multi = plusieurs), recherche, upload dropzone WP-style via `$wire.upload()`. Après confirmation : dispatch `media-picked` avec `{ statePath, ids, medias }` (url + alt + fileName de chaque média), consommé par les champs `MediaPicker` correspondants.

### Bascule Lunar — via `ResourceExtension` (pas de subclass)

**Pourquoi pas de subclass ?** Les Page classes Lunar (`EditProduct`, `ManageProductX`, etc.) codent en dur `protected static string $resource = ProductResource::class;`. Une subclass `MdeProductResource` ne serait donc pas interrogée par ces pages lors du rendu de la sub-navigation → tabs cassés ou routes manquantes.

**Solution retenue** : `app/Filament/Extensions/HideLunarMediaExtension.php` (étend `ResourceExtension`) implémente 4 hooks déclenchés via `LunarPanelManager::callHook()` :

- `extendPages(array $pages)` → `unset($pages['media'])` → la route `/media` n'est pas enregistrée
- `extendSubNavigation(array $pages)` → retire `Manage{Product,Collection,Brand}Media::class`
- `getRelations(array $managers)` → retire `MediaRelationManager::class`
- `extendTable(Table $table)` → filtre toute `SpatieMediaLibraryImageColumn` (utile pour la liste Brand)

Enregistré dans `AppServiceProvider::register()` sous trois clés (`ProductResource`, `CollectionResource`, `BrandResource`) — ces hooks se déclenchent car `ExtendsPages` / `ExtendsSubnavigation` / etc. appellent `callStaticLunarHook()` avec `static::class` = la resource Lunar d'origine.

Aucune subclass MDE créée pour Product/Collection/Brand. Aucune ligne dans `swapLunarResources()`. Les URLs restent identiques à Lunar (`/admin/products`, etc.).

### Modèles CMS migrés

`Post`, `Page`, `HomeSlide`, `HomeTile`, `HomeOffer` : trait `HasMediaAttachments` ajouté. Colonnes `cover_url` / `image_url` **supprimées** par migration `2026_04_17_100100_migrate_cms_images_to_mediables.php` qui tente un best-effort match par `basename(file_name)` avant drop. Un accessor `getImageUrlAttribute()` / `getCoverUrlAttribute()` renvoyant `firstMediaUrl($group)` maintient la compatibilité des blade views storefront.

### Points d'attention

- **Spatie / Lunar Media natif** : le système reste techniquement accessible via `$product->getMedia(...)` (le trait `HasMedia` Spatie est toujours sur les modèles Lunar). Seul l'admin est caché. Le storefront doit migrer ses appels vers `$product->firstMediaUrl('gallery')` pour pointer sur `mde_mediables`.
- **Données Lunar existantes** : aucune migration automatique de `media_has_models` → `mde_mediables`. Prévoir un artisan `mde:migrate-lunar-media` en phase 2 si besoin de conserver les galeries produits existantes.
- **Upgrade Lunar** : la reflection sur `LunarPanelManager::$resources` et les overrides de `getDefault*()` dépendent de l'API interne. À revérifier à chaque upgrade Lunar majeur.

## 14. Documentation externe

- [Lunar](https://docs.lunarphp.com) — **servie aussi via le MCP `lunar-docs`**
- [Filament 3](https://filamentphp.com/docs/3.x)
- [Laravel 11](https://laravel.com/docs/11.x)
- [Filament Shield](https://filamentphp.com/plugins/bezhansalleh-shield)
- [Laravel Boost](https://laravel.com/docs/boost)
- [lunarphp/stripe](https://github.com/lunarphp/stripe)
- [lunarphp/table-rate-shipping](https://github.com/lunarphp/table-rate-shipping)

---

_2026-04-17 — Admin CMS storefront (§15.12) : groupe Filament « Storefront » avec 7 resources (Slides, Tiles, Offers, Posts, Pages, Newsletter, Stores) + page Paramètres (contact/bannière/social/USPs/collection vedette). Table `mde_storefront_settings` key/value + modèle `Setting` avec cache 1h. Surcharge automatique de `config('mde-storefront.*')` au boot. Cart drawer storefront (side-panel qui s'ouvre à l'add-to-cart)._

_2026-04-17 — Frontoffice B2B pro MDE complet (§15) : 7 nouveaux packages (storefront + customer-auth + account + purchase-lists + quick-order + storefront-cms + store-locator), design system bleu pro, price gating strict (Connectez-vous pour voir vos prix), inscription auto + vérif SIRET INSEE V3, mega-menu cache 1h, catalogue faceté via catalog-features, home CMS (slides + tiles + offers + posts + pages), magasins Leaflet, autocomplete header DB. Compte pro de test : `thierry.leroy@mde-distribution.test` / `testing123`._

_2026-04-17 — Admin dev credentials stabilisés via `MdeAdminUserSeeder` idempotent (§6.1) : `admin@mde-distribution.fr` / `testing123` toujours restaurés par `make fresh`/`make install`. Overridable via `MDE_ADMIN_EMAIL` / `MDE_ADMIN_PASSWORD`._

_2026-04-17 — Système de médias unifié `mde_mediables` (§13.ter) : table pivot polymorphique universelle (media_id + mediable_type/id + mediagroup + position) remplaçant l'usage natif Lunar/Spatie côté admin. Trait `HasMediaAttachments`, champ Filament `MediaPicker` single/multi, modal Livewire globale réutilisable. 3 subclasses Lunar (`MdeProductResource`, `MdeCollectionResource`, `MdeBrandResource`) via reflection swap retirent les sous-pages/tabs media natifs. Modèles CMS (Post/Page/HomeSlide/HomeTile/HomeOffer) migrés, colonnes `cover_url`/`image_url` droppées après best-effort match._

_2026-04-17 — Port du Lunar Livewire Starter Kit comme base du frontoffice (§13.bis) : 6 pages Livewire (`/`, `/search`, `/collections/{slug}`, `/products/{slug}`, `/checkout`, `/checkout/success`), 5 composants (Cart, AddToCart, Navigation, CheckoutAddress, ShippingOptions), layouts storefront/checkout, trait `FetchesUrls`, view component `ProductPrice`. Écarts : pas de `ShippingModifier` factice (drivers réels §5), pas d'override `Product`, pas de sanctum/meilisearch/predis. NPM ajoute `@tailwindcss/forms`, `@ryangjchandler/alpine-clipboard`. `config/livewire.php` publié (layout par défaut = `layouts.storefront`)._
