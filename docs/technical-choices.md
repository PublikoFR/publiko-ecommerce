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
- **Drag-n-drop** : **SortableJS 1.15.2** chargé via **CDN** (`cdn.jsdelivr.net`) directement dans la blade — pas de dépendance npm, pas d'entry Vite admin. Alpine component `treeManager` inline attache Sortable sur chaque `<ul data-sortable="...">` et appelle `$wire.moveCollection / moveFeatureValue / moveFeatureFamily` au `onEnd`. Réinitialisation post-Livewire via `Livewire.hook('morph.updated')`.
- **Import / export JSON** : 1 fichier par arbre (header actions séparés). Format versionné (`version: 1`). Import = transaction + `fixTree()` final côté collections, `updateOrCreate` par handle côté features (préserve l'ID existant).

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

## 13. Documentation externe

- [Lunar](https://docs.lunarphp.com) — **servie aussi via le MCP `lunar-docs`**
- [Filament 3](https://filamentphp.com/docs/3.x)
- [Laravel 11](https://laravel.com/docs/11.x)
- [Filament Shield](https://filamentphp.com/plugins/bezhansalleh-shield)
- [Laravel Boost](https://laravel.com/docs/boost)
- [lunarphp/stripe](https://github.com/lunarphp/stripe)
- [lunarphp/table-rate-shipping](https://github.com/lunarphp/table-rate-shipping)

---

_Dernière mise à jour : 2026-04-11 — MCP servers laravel-boost + lunar-docs ajoutés + CLAUDE.md refondu en instructions pures._
