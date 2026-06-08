# Shipping — drivers Chronopost, Colissimo, table-rate

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

**Seed de base** (`PkoShippingSeeder`) :

- 1 zone `France métropolitaine` (type `country`, rattachée à FR)
- 3 méthodes : `mde-standard` (ship-by par poids), `mde-pickup` (collection, retrait entrepôt), `mde-free` (free-shipping dès 500 €)
- 3 rates attachés avec brackets : 4 paliers pour le standard (690/990/1490/1990 cents), 1 bracket à 0 pour pickup + free

### 5.2 Phase 2 — Chronopost + Colissimo dynamiques

**Décision** : intégration SOAP de **Chronopost** et **Colissimo** via 3 packages sous `packages/pko/`. Sendcloud écarté pour coût SaaS.

**Architecture** :

| Package | Namespace | Rôle |
|---|---|---|
| `shipping-common` | `Pko\ShippingCommon\` | Contracts, DTOs, modèle `CarrierShipment`, Job, Observer, `ZoneResolver`, `WeightCalculator`, resource Filament « Envois transporteurs » |
| `shipping-chronopost` | `Pko\ShippingChronopost\` | Client SOAP (SDK `ladromelaboratoire/chronopostws`), `ChronopostModifier`, page Filament « Configuration Chronopost » |
| `shipping-colissimo` | `Pko\ShippingColissimo\` | Client SOAP (SDK `wsdltophp/package-colissimo-postage`), `ColissimoModifier`, page Filament « Configuration Colissimo » |

**Choix critique — grilles statiques vs API temps réel au checkout** :

- **Choix** : grilles statiques versionnées dans `config/chronopost.php` / `config/colissimo.php`.
- **Raison** : les contrats La Poste sont des grilles annuelles connues. Un appel API QuickCost ajouterait 300–800 ms de latence au checkout et risquerait de le casser en cas d'incident SOAP La Poste.
- **Swap vers temps réel** : remplacer la méthode `CarrierClient::quote()` dans le Client concerné. Interface stable, modifiers et job inchangés.

**Flux** :

1. **Checkout** : les deux `ShippingModifier` custom (enregistrés via `ShippingModifiers::add()`) injectent des `ShippingOption` dans le manifest à partir des grilles statiques. Identifiers : `chronopost.{service}` / `colissimo.{service}`.
2. **Zone** : `ZoneResolver::isMetropole()` filtre France métropolitaine uniquement (skip Corse `20*`, DOM `971`–`978`, étranger).
3. **Poids** : `WeightCalculator::fromCart()` / `fromOrder()` normalise en **kg** (accepte `kg`/`g`/`lb`, throw sur unité inconnue).
4. **Post-paiement** : `OrderShipmentObserver::updated()` observe `Order::payment_status` → transition vers `paid` → dispatche `CreateCarrierShipmentJob(order_id, carrier, service_code)`.
5. **Job async** : `$tries = 5`, `$backoff = [60, 300, 900, 3600, 14400]`. Résout le `CarrierClient` via `app("mde.shipping.carrier.{$carrier}")`, persiste le `CarrierShipment` (table `pko_carrier_shipments`), sauvegarde le PDF dans `storage/app/labels/{order_id}/{carrier}-{tracking}.pdf`. Après 5 échecs → `status = 'failed'` + `error_message`.
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

### 5.3 Credentials — mode env ou base de données

Depuis l'ajout du package `pko/lunar-secrets`, chaque transporteur propose un toggle **Source** dans sa page Filament Config (« Expédition » → Chronopost / Colissimo) :

- **`.env`** (défaut) : credentials lus depuis `CHRONOPOST_ACCOUNT`, `CHRONOPOST_PASSWORD`, `COLISSIMO_CONTRACT`, `COLISSIMO_PASSWORD`, etc.
- **Base de données** : valeurs chiffrées stockées dans `pko_secrets` (cast `encrypted`), éditables depuis l'UI.

Au boot, `SecretsServiceProvider` réécrit `config('chronopost.credentials.*')` / `config('colissimo.credentials.*')` lorsque le module est en mode DB → les Clients SOAP continuent à lire `config()` sans modification. Voir [packages/secrets.md](packages/secrets.md).

Les pages Config transporteurs sont classées sous **« Expédition »** (et non plus « Configuration »), aux côtés de la resource `CarrierShipmentResource`.

### 5.4 Framework transporteurs (2026-04)

Depuis avril 2026 :

- **Plugin Filament unique** `TransportersPlugin` (remplace `ShippingCommonPlugin` + `ChronopostPlugin` + `ColissimoPlugin`).
- **`CarrierRegistry`** singleton — chaque adapter s'enregistre dans son `ServiceProvider::register()` via `afterResolving(CarrierRegistry::class)`.
- **`AbstractCarrierModifier`** — sous-classe déclare 1 ligne (`carrierCode()`), parent gère adresse/zone/poids/quote/addOption.
- **`AbstractCarrierConfigPage`** — rend le formulaire complet (credentials toggle env/DB + services Repeater + grille Repeater) à partir de la `CarrierDefinition`.
- **Tables `pko_carrier_services` et `pko_carrier_grids`** — data migration initiale (`2026_04_21_110100_seed_initial_carrier_data`) sème Chronopost (5 paliers, 3 services) et Colissimo (4 paliers, 2 services) depuis les anciennes valeurs config.
- **Ajouter un nouveau transporteur** : cf. [packages/transporters.md](packages/transporters.md) — ~80 lignes au total.

### 5.5 Tarification live Chronopost (2026-04)

Revirement de la décision initiale « grilles statiques uniquement » (§5.2) : **3 modes au choix** par transporteur, togglés depuis l'admin Filament :

| Mode | Source du prix | Fallback |
|---|---|---|
| `grid` (défaut) | `pko_carrier_grids` en DB | — |
| `live_with_fallback` | API live + cache Redis 24 h | Grille si API KO |
| `live_only` | API live + cache Redis 24 h | Transporteur retiré du checkout si API KO |

**Architecture** :
- `Pko\ShippingChronopost\Services\QuickCostSoapClient` — client SOAP pur sur `QuickcostServiceWS` (impl nous-même, le SDK `ladromelaboratoire/chronopostws` a un stub vide).
- `Pko\ShippingCommon\Pricing\LivePricingResolver` — orchestrateur (cache 24 h, lock anti-herd, fallback, log).
- `Pko\ShippingCommon\Pricing\PricingModeResolver` — lit/écrit `shipping.{carrier}.pricing_mode` dans `pko_storefront_settings`.
- Canal log `shipping-quickcost` (daily, 30 j) — `storage/logs/shipping-quickcost.log`.

**Colissimo** reste en mode `grid` uniquement (pas d'API tarifaire publique). Bouton **Charger les tarifs publics 2026** dans la page Config via `Pko\ShippingColissimo\Data\PublicTariffs2026`.

**Pourquoi ce choix** : cache agressif → 99% cache-hit à latence nulle ; fallback grille → jamais de checkout cassé ; prix frais à 24 h près via l'API. Meilleur compromis perf/fraîcheur/robustesse.

Voir [packages/transporters.md](packages/transporters.md) pour les détails d'implémentation.

### 5.6 Suivi post-envoi (2026-04)

1. **Email de confirmation d'expédition** — `ShipmentCreatedMail` envoyé dès que l'étiquette est générée. Lien "Suivre mon colis" unifié via `laposte.fr/outils/suivre-vos-envois`.
2. **Polling tracking horaire** — command `shipping:poll-tracking` planifiée toutes les heures via `routes/console.php`. Utilise l'API REST La Poste unifiée (`api.laposte.fr/suivi/v2`, clé Okapi) qui couvre **Chronopost ET Colissimo** en un seul client. Normalise les codes événements dans un vocabulaire stable.
3. **Statut Lunar Order** — `Order.status` flippe automatiquement à `dispatched` au moment où l'étiquette est créée, puis à `delivered` dès que l'API La Poste retourne un événement de livraison (`DI1`/`DI2`).

Voir [packages/transporters.md](packages/transporters.md) pour les détails (colonnes DB, mapping codes, webhook futur).

### 5.6bis Gotchas table-rate — résolution des options (2026-06)

`ShippingManifest::getOptions($cart)` renvoie **0 option** silencieusement si l'une de ces conditions de données n'est pas remplie (constaté au premier test checkout, port Lunar) :

1. **Type de zone = `countries` (pluriel)** — le resolver `ShippingZoneResolver` matche `whereType('countries')`. Une zone créée avec `type='country'` (singulier) n'est **jamais** trouvée. Valeurs valides : `unrestricted`, `countries`, `states`, `postcodes`.
2. **Méthodes schedulées contre les groupes clients** — `ShippingRateResolver` rejette toute rate dont `shippingMethod()->customerGroup($groups)->first()` est null. Sans entrée dans `lunar_customer_group_shipping_method` (via `$method->scheduleCustomerGroup($groups)`), **aucune** option ne sort, quel que soit le groupe du client. `PkoShippingSeeder` schedule les 3 méthodes sur tous les groupes → doit donc tourner **après** `PkoCustomerGroupSeeder` dans `DatabaseSeeder`.

Couvert par `tests/Feature/SeedersTest::test_shipping_seeder_creates_zone_methods_rates` (assertions type `countries` + méthodes schedulées).

### 5.8 Frais de port offert par produit — dropshipping (2026-06)

**Cas d'usage** : le fournisseur expédie directement au client final et les frais de port sont inclus dans le prix d'achat. Les produits concernés sont flaggés `pko_free_shipping = true` en base.

**Stockage** : colonne `pko_free_shipping BOOLEAN NOT NULL DEFAULT 0` sur `lunar_products`, ajoutée via migration custom `2026_06_08_100000_add_pko_free_shipping_to_lunar_products.php` (index présent pour les requêtes efficients).

**Logique checkout** :

| Panier | Comportement |
|--------|-------------|
| 100% flaggés | `FreeShippingModifier` ajoute 1 option "Livraison offerte" (0 €). Les carriers skippent naturellement car `fromCartTaxable()` = 0. |
| Mixte | Carriers calculent le poids/prix sur les lignes non-flaggées uniquement (`WeightCalculator::fromCartTaxable()`). Aucune option "free" injectée. |
| Aucun flaggé | Comportement standard inchangé. |

**Composants modifiés** :

- `WeightCalculator::fromCartTaxable(Cart)` — nouveau, filtre les lignes `pko_free_shipping = true`.
- `WeightCalculator::allLinesFreeShipping(Cart)` — nouveau, retourne `true` si toutes les lignes sont flaggées.
- `AbstractCarrierModifier::handle()` — utilise désormais `fromCartTaxable()` au lieu de `fromCart()`.
- `FreeShippingModifier` — enregistré dans `ShippingCommonServiceProvider`, injecte l'option gratuite quand applicable.
- `ProductFreeShippingExtension` — extension Filament (section "Livraison / Logistique" dans la fiche produit).

**Front** : badge "Livraison offerte" (vert) sur la fiche produit storefront quand `pko_free_shipping = true`, remplace "Livraison 24/48h".

### 5.7 Hors scope shipping

- Points relais (`BPR` Colissimo, `Chrono Relais`)
- Tracking webhook (polling ou push transporteur)
- Retour / annulation d'envoi (`cancelSkybill`)
- Livraison hors France métropolitaine (Corse, DOM, étranger)
- Sendcloud (alternative SaaS écartée pour coût)

---

