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
2. **Post-paiement** : `OrderShipmentObserver` détecte `payment_status → paid` et dispatche `CreateCarrierShipmentJob(order_id, carrier, service_code)`.
3. **Job async** : résout le Client via `app("pko.shipping.carrier.{$carrier}")`, persiste `CarrierShipment` (table `pko_carrier_shipments`), sauvegarde le PDF d'étiquette.
4. **Admin** : resource Filament `CarrierShipmentResource` (groupe **Expédition**) liste les envois, pages config par transporteur sous le même groupe.

## Tables DB

| Table | Rôle |
|---|---|
| `pko_carrier_shipments` | Envois créés, label, tracking, statut |
| `pko_carrier_services` | Services activables par transporteur (`chronopost.13`, `colissimo.DOM`…) — **éditable en admin** |
| `pko_carrier_grids` | Paliers tarifaires `max_kg` / `price_cents` — **éditable en admin** |
| `pko_secrets` | Credentials API chiffrés (via package `pko/lunar-secrets`) |

## Navigation Filament

Groupe **« Expédition »** contient :

- `Envois transporteurs` (resource `CarrierShipmentResource`)
- `Chronopost` (page `ChronopostConfig`)
- `Colissimo` (page `ColissimoConfig`)
- `[Nouveau transporteur]` (auto-découvert via `CarrierRegistry`)

Un seul plugin Filament (`TransportersPlugin`) lit le `CarrierRegistry` et enregistre toutes les pages config.

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
