# Transporteurs PKO — architecture

Framework d'intégration de transporteurs pour Lunar, porté par `packages/pko/shipping-common`. Ajouter un transporteur revient à écrire ~80 lignes (un Client + un Modifier de 8 lignes + une ConfigPage de 10 lignes + un ServiceProvider qui enregistre au Registry).

## Vue d'ensemble

| Package | Namespace | Rôle |
|---|---|---|
| `pko/lunar-shipping-common` | `Pko\ShippingCommon\*` | Foundation : `CarrierClient` contract, `CarrierRegistry`, `AbstractCarrierModifier`, `AbstractCarrierConfigPage`, `TransportersPlugin`, repos DB (services + grilles), `CarrierShipmentResource`, observer post-paiement |
| `pko/lunar-shipping-chronopost` | `Pko\ShippingChronopost\*` | Adapter Chronopost (Client SOAP, Modifier/ConfigPage triviales) |
| `pko/lunar-shipping-colissimo` | `Pko\ShippingColissimo\*` | Adapter Colissimo (idem) |
| `pko/lunar-secrets` | `Pko\Secrets\*` | Credentials env/DB (cf. [secrets.md](secrets.md)) |

## Flux runtime

1. **Checkout** : chaque `AbstractCarrierModifier` (sous-classé par adapter) est injecté dans `ShippingModifiers`. Il extrait adresse, filtre la zone (France métropolitaine par défaut), calcule le poids panier, appelle le Client, et pousse un `ShippingOption` par service dans le manifest.
2. **Post-paiement — multi-expédition (L6)** : `OrderShipmentObserver` détecte `payment_status → paid`, charge les lignes avec `purchasable.product` + map de `Supplier` par ID, délègue à `ShipmentSplitter::split()` qui retourne des `ShipmentGroup[]` (un par origine : `weklo`, `supplier_direct`, `supplier_via_weklo`). Seul le groupe `weklo` dispatche `CreateCarrierShipmentJob`. Les groupes fournisseur créent un `CarrierShipment(status=pending)` sans appel API.
3. **Job async** : résout le Client via `app("pko.shipping.carrier.{$carrier}")`, persiste `CarrierShipment` (table `pko_carrier_shipments`, clé unique `(order_id, carrier, origin)`), sauvegarde le PDF d'étiquette.
4. **Admin** : resource Filament `CarrierShipmentResource` (groupe **Expédition**) liste les envois. Action "Relancer" visible uniquement pour `origin=weklo`.

## Tables DB

| Table | Rôle |
|---|---|
| `pko_carrier_shipments` | Envois créés, label, tracking, statut, **origin** (`weklo` / `supplier_direct` / `supplier_via_weklo`) — clé unique `(order_id, carrier, origin)` |
| `pko_carrier_services` | Services activables par transporteur (`chronopost.13`, `colissimo.DOM`…) — **éditable en admin** |
| `pko_carrier_grids` | Paliers tarifaires `max_kg` / `price_cents` — **éditable en admin** |
| `pko_secrets` | Credentials API chiffrés (via package `pko/lunar-secrets`) |

## Navigation Filament — Cluster Expédition

Un **seul item** de sidebar "Expédition" (cluster `Pko\ShippingCommon\Filament\Clusters\Shipping`, slug `/admin/expedition/*`). À l'ouverture, Filament affiche la sub-nav à droite (`SubNavigationPosition::End`) scindée en **2 groupes collapsibles** via `$navigationGroup` :

### Groupe "Expédition" (opérations d'expédition)

1. **Méthodes d'expédition** — `PkoShippingMethodResource` (subclassée de Lunar)
2. **Zones d'expédition** — `PkoShippingZoneResource`
3. **Listes d'exclusion d'expédition** — `PkoShippingExclusionListResource`
4. **Envois Transporteurs** — `CarrierShipmentResource`

Le groupe "Expédition" vient de `__('lunarpanel.shipping::plugin.navigation.group')` sur les 3 resources Lunar, et de `getNavigationGroup() => 'Expédition'` sur `CarrierShipmentResource`.

### Groupe "Transporteurs" (configurations par carrier)

1. **Chronopost** — `ChronopostConfig`
2. **Colissimo** — `ColissimoConfig`
3. (futur DPD/UPS/… — hérite automatiquement via `AbstractCarrierConfigPage`)

`AbstractCarrierConfigPage::$navigationGroup = 'Transporteurs'` → toutes les ConfigPages de transporteur remontent dans ce second groupe collapsible.

### Swap des resources Lunar vers le cluster

Les resources Lunar `vendor/lunarphp/table-rate-shipping` sont immuables. Solution (pattern §3.2 CLAUDE.md) :

- **Subclasses** dans `app/Filament/Resources/PkoShipping{Method,Zone,ExclusionList}Resource.php` avec `$cluster = Shipping::class` + `getDefaultPages()` redéclaré pour que les pages pointent vers la Pko-variante.
- **`SwapLunarShippingResourcesPlugin`** (chaîné après `ShippingPlugin::make()`) effectue par réflexion le remplacement `ShippingXResource::class → PkoShippingXResource::class` dans l'array `$panel->resources`.

### Ajouter une nouvelle page / resource au cluster

Déclarer `protected static ?string $cluster = Pko\ShippingCommon\Filament\Clusters\Shipping::class;` sur la Page ou la Resource. Elle apparaît automatiquement comme onglet.

### Un seul plugin Filament auto-discover

`TransportersPlugin` lit le `CarrierRegistry` et enregistre toutes les pages config des carriers en 1 ligne de code.

## Ajouter un nouveau transporteur

Exemple « DPD » :

### 1. Créer `packages/pko/shipping-dpd/`

- `composer.json` — `name: pko/lunar-shipping-dpd`, require `pko/lunar-shipping-common`, `pko/lunar-secrets`. Provider : `Pko\ShippingDpd\ShippingDpdServiceProvider`.
- `src/Services/DpdClient.php` — implémente `Pko\ShippingCommon\Contracts\CarrierClient`.
- `src/Modifiers/DpdModifier.php` — 8 lignes :

```php
class DpdModifier extends AbstractCarrierModifier {
    protected function carrierCode(): string { return 'dpd'; }
}
```

- `src/Filament/Pages/DpdConfig.php` — 15 lignes :

```php
class DpdConfig extends AbstractCarrierConfigPage {
    protected static string $view = 'pko-shipping-common::pages.carrier-config';
    protected static ?int $navigationSort = 22;

    protected function carrierCode(): string { return 'dpd'; }
    protected static function navigationLabel(): ?string { return 'DPD'; }
}
```

- `src/ShippingDpdServiceProvider.php` — enregistre Client singleton, `CarrierRegistry::register(new CarrierDefinition(...))` via `afterResolving`, ajoute le Modifier à `ShippingModifiers`.

### 2. Enregistrer dans `AppServiceProvider::registerSecretModules()`

```php
Secrets::register('dpd',
    keys: ['account' => 'DPD_ACCOUNT', 'secret' => 'DPD_SECRET'],
    defaultSource: 'env',
    label: 'DPD',
    configMap: ['account' => 'dpd.credentials.account', 'secret' => 'dpd.credentials.secret'],
);
```

### 3. Ajouter `"pko/lunar-shipping-dpd": "@dev"` dans `composer.json` racine.

### 4. `make composer CMD='update pko/lunar-shipping-dpd'` + `make artisan CMD='migrate'`.

Et c'est tout : le transporteur apparaît dans « Expédition » avec son formulaire complet (credentials env/DB + services + grille), le Modifier injecte les quotes au checkout, le Job crée l'étiquette post-paiement.

## Édition des grilles et services

Depuis la page Filament Config d'un transporteur :

- Section **Credentials** : toggle env/DB + inputs conditionnels (cf. [secrets.md](secrets.md)).
- Section **Services activés** : Repeater (code, libellé, actif).
- Section **Grille tarifaire** : Repeater (max_kg, prix en cents, service optionnel).

À la soumission (`AbstractCarrierConfigPage::save()`), les services et paliers sont réécrits (delete + insert) dans une transaction, puis le cache des repositories est flushé.

## Modes de tarification

Chaque transporteur peut fonctionner selon 3 modes, togglés par l'admin depuis sa page Config (uniquement si le transporteur a `CarrierDefinition::$supportsLive = true`) :

| Mode | Source du prix | Latence checkout | Robustesse |
|---|---|---|---|
| `grid` (défaut) | Table `pko_carrier_grids` | ~0 ms | Toujours disponible |
| `live_with_fallback` | API live avec cache Redis 24 h + fallback grille | ~0 ms (cache hit) / ~500 ms (miss) | Jamais de checkout cassé : fallback grille si API down |
| `live_only` | API live avec cache Redis 24 h, **pas** de fallback | ~0 ms / ~500 ms | Si API down → le transporteur disparaît du checkout (les autres continuent) |

### Support par transporteur

| Transporteur | Supporte live ? | Raison |
|---|---|---|
| Chronopost | ✅ | API SOAP `QuickcostServiceWS` (WSDL public, credentials contrat) |
| Colissimo | ❌ | La Poste n'expose **aucune** API tarifaire publique pour Colissimo. Grille DB uniquement. |

### Cache live

- Clé : `pko.shipping.{carrier}.quickcost.{service}.{depZip}.{arrZipPrefix}.{weightBucket}` (weight arrondi au kg sup).
- TTL : 24 h.
- Tag Redis : `pko.shipping.{carrier}` → permet un flush ciblé par transporteur.
- Bouton **Vider le cache tarifs live** dans la page Config (actions header).
- Lock `Cache::lock` pour éviter le thundering herd lors d'un cache miss concurrent.

### Logging

Canal dédié `shipping-quickcost` (`config/logging.php` → daily, 30 jours de rétention, `storage/logs/shipping-quickcost.log`). Logue :
- `info` : cache miss + durée de l'appel SOAP.
- `warning` : échec de l'appel live (message, exception class).
- `info` : résumé quote lorsqu'au moins un service a échoué.

## Presets publics

Chaque package transporteur peut bundler les tarifs publics de l'année dans `packages/pko/shipping-{carrier}/src/Data/PublicTariffs{YEAR}.php`.

**Exemple Colissimo** : `Pko\ShippingColissimo\Data\PublicTariffs2026` contient les paliers et services de la grille publique La Poste. Un bouton **Charger les tarifs publics 2026** dans `ColissimoConfig` remplace services + grille en 1 clic (transaction + flush cache).

**Maintenance annuelle** : créer `PublicTariffs2027.php`, mettre à jour la classe référencée dans `ColissimoConfig::getHeaderActions()`.

## Suivi post-envoi

### Notification email au client

Dès que `CreateCarrierShipmentJob` a généré l'étiquette avec succès :

1. `Order.status` bascule à `dispatched` (Lunar).
2. `ShipmentCreatedMail` (Mailable, queued) est envoyé au client avec :
   - Référence commande
   - Nom du transporteur
   - N° de suivi
   - Lien "Suivre mon colis" vers `laposte.fr/outils/suivre-vos-envois?code={tracking}` (unifié Chronopost + Colissimo)
3. `notified_customer_at` est horodaté sur le `CarrierShipment` pour éviter les doubles envois.

Destinataire résolu par priorité :
1. `order->shippingAddress->contact_email`
2. `order->billingAddress->contact_email`
3. `order->customer->email`

Template Blade : `pko-shipping-common::emails.shipment-created` (surchargeable via `php artisan vendor:publish`).

### Polling tracking La Poste

Une unique API REST couvre **tous les produits La Poste** (Colissimo, Chronopost, Lettre suivie…) : `https://api.laposte.fr/suivi/v2/idships/{tracking}`. Une seule clé API (`LAPOSTE_API_KEY`, alias Okapi key) à obtenir sur developer.laposte.fr.

**Module `laposte` dans `pko/lunar-secrets`** : toggle env/DB comme les autres modules.

**Command artisan** : `shipping:poll-tracking [--limit=100] [--min-age=1]`.

**Schedule** : lancée **toutes les heures** via `routes/console.php`.

**Logique** :
1. Sélectionne les `CarrierShipment` où `status=created` + tracking_number présent + delivery_status non-terminal + dernière poll > 1 h.
2. Pour chacun, appelle `LaPosteTrackingClient::track()`.
3. Normalise le code événement La Poste dans un vocabulaire stable : `in_transit` / `out_for_delivery` / `delivered` / `returned` / `failed` / `unknown`.
4. Persiste `delivery_status`, `delivery_status_updated_at`, `delivered_at`, `tracking_events` (JSON).
5. Si transition vers `delivered` → `Order.status` passe à `delivered` (nouveau statut Lunar ajouté dans `config/lunar/orders.php`).

**Colonnes ajoutées à `pko_carrier_shipments`** :
- `delivery_status` (varchar 32, nullable, indexé avec `carrier`)
- `delivery_status_updated_at` (timestamp)
- `delivered_at` (timestamp)
- `tracking_events` (json)
- `notified_customer_at` (timestamp)

**Admin Filament** : resource `Envois transporteurs` affiche désormais :
- Badge "Livraison" (en transit / en livraison / livré / retourné / échec) + filtre
- Icône "Email envoyé" (trueIcon/falseIcon)

### Webhook La Poste (non implémenté)

Pour un monitoring plus réactif (< 5 min vs 1 h), La Poste propose un webhook d'abonnement par numéro de suivi. Non implémenté en v1 : le polling horaire suffit pour la majorité des cas. Peut être ajouté sans casser l'existant — il suffirait de brancher un endpoint qui réutilise la logique `PollTrackingCommand::applyStatus()`.

## Décisions

| Question | Choix | Raison |
|---|---|---|
| Unité de poids | kg | `WeightCalculator` normalise (accepte `kg`/`g`/`lb`) |
| Zone v1 | France métropolitaine | `ZoneResolver::isMetropole` — override possible via `AbstractCarrierModifier::shouldQuote()` |
| Stockage grille/services | DB (`pko_carrier_grids`, `pko_carrier_services`) | Éditable depuis l'admin sans redéploiement |
| Credentials | `pko/lunar-secrets` avec toggle env/DB par module | Flexibilité sécurité vs ergonomie, choisi par l'opérateur |
| Plugin Filament | Un seul (`TransportersPlugin`) auto-discovery via `CarrierRegistry` | Ajouter un transporteur ne nécessite aucune modif de `AppServiceProvider::LunarPanel` |
| Branding | `pko-*` uniquement (plugin IDs, view namespaces) | Conformité CLAUDE.md §3.0 |

## Multi-expédition par origine de stock (L6 2026-06)

### `ShipmentSplitter`

Service pur (`Pko\ShippingCommon\Shipping\ShipmentSplitter`) sans accès DB, testable avec des stdClass mocks. Prend une collection de lignes (avec `purchasable.product`) et une collection de `Supplier` keyés par ID. Retourne un tableau de `ShipmentGroup(origin, lineCount)`.

**Algorithme de résolution d'origine** :
- `pko_supplier_id === null` → `weklo`
- `pko_supplier_id` défini mais supplier absent dans la map → `weklo` (fail-safe)
- Supplier `bl_neutre = true` → `supplier_direct`
- Supplier `bl_neutre = false` → `supplier_via_weklo`

### Constantes d'origine sur `CarrierShipment`

- `ORIGIN_WEKLO = 'weklo'`
- `ORIGIN_SUPPLIER_DIRECT = 'supplier_direct'`
- `ORIGIN_SUPPLIER_VIA_WEKLO = 'supplier_via_weklo'`

### Commande sur devis

**Nouveau statut Lunar** : `awaiting-quote` (label "En attente de devis", couleur ambre) dans `config/lunar/orders.php`.

**Pipeline de création** : `MarkQuoteOrderAwaitingQuote` (dernier pipe de `creation`) bascule le statut si une ligne a `pko_quote_only = true`.

**Extension Filament** : `OrderQuoteActionsExtension` (enregistrée sur `ManageOrder` dans `AppServiceProvider`) ajoute l'action **Envoyer lien de paiement** (visible si `status = awaiting-quote`). Modal avec saisie du montant transport. Génère `URL::signedRoute('pko.quote.pay', ...)` (7 jours) et envoie `QuotePaymentLinkMail`.

**Route** : `GET /paiement-devis/{order}` (signed, middleware `signed`) → `QuotePaymentController`. Page de paiement stub (TODO : intégration Stripe).

## Resources back-office — Fournisseurs & suppléments (L3 2026-06)

### SupplierResource

CRUD Filament pour `pko_suppliers` (modèle `Pko\ShippingCommon\Models\Supplier`). Cluster **Expédition**, `navigationSort = 20`.

Champs : `name`, `bl_neutre` (toggle "BL neutre / livraison directe fournisseur → client"), `lead_time_min_days`, `lead_time_max_days`, `notes`.

Enregistré via `TransportersPlugin::register()` — pas besoin de toucher `AppServiceProvider`.

### ShippingSurchargeResource

CRUD Filament pour `pko_shipping_surcharges` (modèle `Pko\ShippingCommon\Models\ShippingSurcharge`). Cluster **Expédition**, `navigationSort = 30`.

Champs : `code` (unique, snake_case), `label`, `mode` (`auto` / `quote` / `rebill`), `amount_cents` (nullable), `rule` (JSON, nullable), `enabled`.

**Données de référence** (seedées par `PkoShippingSurchargesSeeder`) :

| code | mode |
|---|---|
| `corse` | quote |
| `zone_difficile` | quote |
| `hors_normes` | quote |
| `manutention` | quote |
| `livraison_samedi` | auto |
| `assurance` | rebill |
| `correction_adresse` | rebill |
| `retour_expediteur` | rebill |
| `transport_specifique` | quote |

### Traductions

Toutes les clés dans `packages/pko/shipping-common/lang/fr/admin.php`, namespace `pko-shipping-common::admin.*`. Le `ShippingCommonServiceProvider` charge le namespace via `loadTranslationsFrom`.

