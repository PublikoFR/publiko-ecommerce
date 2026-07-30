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
- 3 méthodes : `pko-standard` (ship-by par poids), `pko-pickup` (collection, retrait entrepôt), `pko-free` (free-shipping dès 500 €)
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
4. **Post-paiement — multi-expédition (L6)** : `OrderShipmentObserver::updated()` observe `Order::payment_status` → transition vers `paid` → charge les lignes avec `purchasable.product`, construit une map de `Supplier` par `pko_supplier_id`, puis délègue à `ShipmentSplitter::split(Collection $lines, Collection $suppliers)` qui retourne des `ShipmentGroup[]` (un par origine). Pour chaque groupe :
   - `weklo` (pas de `pko_supplier_id`) → dispatche `CreateCarrierShipmentJob(order_id, carrier, service_code, 'weklo')` comme avant.
   - `supplier_direct` (supplier `bl_neutre=true`) → crée `CarrierShipment` directement (status `pending`), **pas d'appel API**.
   - `supplier_via_weklo` (supplier `bl_neutre=false`) → idem, enregistrement pour suivi futur, **pas d'appel API immédiat**.
5. **Job async** : `$tries = 5`, `$backoff = [60, 300, 900, 3600, 14400]`. Résout le `CarrierClient` via `app("pko.shipping.carrier.{$carrier}")`, persiste le `CarrierShipment` (table `pko_carrier_shipments`, clé unique `(order_id, carrier, origin)`), sauvegarde le PDF dans `storage/app/labels/{order_id}/{carrier}-{tracking}.pdf`. Après 5 échecs → `status = 'failed'` + `error_message`.
6. **Admin** : resource Filament `CarrierShipmentResource` (groupe **Expédition**) → liste, filtre par carrier/statut/origin, action « Télécharger étiquette », action « Relancer » pour les échecs d'origine `weklo` uniquement.

### 5.3 Commande sur devis (`awaiting-quote`)

**Principe** : certains produits (`pko_quote_only=true`) ne peuvent pas être payés immédiatement (prix transport inconnu). À la création d'une commande contenant de tels produits, le statut est automatiquement basculé vers `awaiting-quote` via le pipeline `MarkQuoteOrderAwaitingQuote` (enregistré en dernier dans `config/lunar/orders.php → pipelines.creation`).

**Flux client (interception checkout storefront)** : implémenté dans `app/Livewire/CheckoutPage.php`.

- Propriété calculée `isQuoteOnlyCart` : `true` dès qu'une ligne du panier porte un produit `pko_quote_only`.
- `CheckoutPage::checkout()` bifurque : si `isQuoteOnlyCart`, on appelle directement `$cart->createOrder()` (le pipeline pose le statut `awaiting-quote`) puis on marque la commande `placed_at`, **sans** passer par `Payments::cart()->authorize()`. Aucun PaymentIntent / encaissement n'est déclenché.
- L'étape « Payment » du checkout (`resources/views/partials/checkout/payment.blade.php`) masque les options carte/espèces et affiche un bandeau « devis transport » + un bouton « Demander un devis ». La page de succès (`checkout-success-page.blade.php`) répète le message quand `order.status === 'awaiting-quote'`.
- Message client : « Votre commande nécessite un devis transport. Vous recevrez un lien de paiement avec le montant final une fois les frais de port calculés. »
- Un panier sans produit `pko_quote_only` suit le flux de paiement standard, inchangé.
- Couverture : `tests/Feature/Shipping/CheckoutQuoteInterceptionTest.php` (bifurcation + absence de paiement) et `QuoteOrderTest.php` (pipeline statut).

**Flux opérateur** :
1. Le client passe la commande → statut `awaiting-quote` (payment_status reste `unpaid`).
2. L'opérateur consulte la commande dans Filament (page ManageOrder), voit l'action **Envoyer lien de paiement** (`OrderQuoteActionsExtension`).
3. Il saisit le montant des frais de port HT (en centimes) et valide.
4. Le système génère une URL signée (`URL::signedRoute('pko.quote.pay', ['order' => $id, 'transport_cents' => $cents])`, valide 7 jours) et envoie un e-mail via `QuotePaymentLinkMail`.
5. Le client clique sur le lien → `GET /paiement-devis/{order}` (signé, contrôlé par `QuotePaymentController::show`).

**Flux de paiement Stripe** (`QuotePaymentController`, package `shipping-common`) :

- **`show()`** (route `pko.quote.pay`, signée, middleware `web` + `signed`) : vérifie la signature + le statut `awaiting-quote` (sinon 403/410). Calcule `montant = order->total + transport_cents` (le `transport_cents` provient de l'URL signée, donc infalsifiable), crée (ou réutilise) un **Stripe PaymentIntent** via l'addon `lunarphp/stripe` existant (`\Stripe\PaymentIntent::create`, jamais Cashier — cf. CLAUDE.md), persiste son `intent_id` dans `order.meta['quote_payment']`, puis rend le **Stripe Payment Element** (`quote-payment.blade.php`, branding neutre via `brand_name()`).
- **`confirm()`** (route `pko.quote.pay.confirm`, **non signée** : Stripe ajoute ses propres query params `payment_intent`/`redirect_status` qui casseraient la signature) : `return_url` de Stripe. Vérifie le PaymentIntent **côté serveur** (statut `succeeded`) — l'intent est lié à la commande en base, donc pas besoin de signature. Sur succès et si la commande est encore `awaiting-quote` (garde d'idempotence dans une transaction) : enregistre une `Lunar\Models\Transaction` (type `capture`, driver `stripe`), bascule la commande en `payment-received` et reporte `transport_cents` sur `shipping_total`/`total` (réconciliation montant chargé = montant commande).

**Déclenchement des expéditions** : le passage en `payment-received` est capté par `OrderShipmentObserver` (voir ci-dessous), qui crée les `CarrierShipment` / dispatch `CreateCarrierShipmentJob` par origine.

> ⚠️ **Correctif transverse** : `OrderShipmentObserver` réagissait à une colonne `payment_status` **inexistante** sur `lunar_orders` (seule `status` existe ; l'addon Stripe mappe un PaymentIntent réussi vers `payment-received`). L'observer écoute désormais `status` ∈ {`paid`, `payment-received`} — ce qui répare aussi la création d'expéditions du **checkout normal**, pas seulement du flux devis.

**Statut Lunar** : `awaiting-quote` (label "En attente de devis", couleur ambre). Ajouté dans `config/lunar/orders.php → statuses`.

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

### 5.4 Framework transporteurs (2026-04, mis à jour L4 2026-07)

Depuis avril 2026 :

- **Plugin Filament unique** `TransportersPlugin` (remplace `ShippingCommonPlugin` + `ChronopostPlugin` + `ColissimoPlugin`).
- **`CarrierRegistry`** singleton — chaque adapter s'enregistre dans son `ServiceProvider::register()` via `afterResolving(CarrierRegistry::class)`.
- **`AbstractCarrierConfigPage`** — rend le formulaire complet (credentials toggle env/DB + services Repeater + grille Repeater) à partir de la `CarrierDefinition`.
- **Tables `pko_carrier_services` et `pko_carrier_grids`** — data migration initiale (`2026_04_21_110100_seed_initial_carrier_data`) sème Chronopost (5 paliers, 3 services) et Colissimo (4 paliers, 2 services) depuis les anciennes valeurs config.
- **Ajouter un nouveau transporteur** : cf. [packages/transporters.md](packages/transporters.md) — ~80 lignes au total.

> **L4 (2026-07)** : `AbstractCarrierModifier` supprimé — remplacé par `ShippingCalculator` (cf. §5.10 / §5.12). Les sous-classes `ChronopostModifier` et `ColissimoModifier` sont supprimées. Seul `UnifiedShippingModifier` reste dans le pipeline Lunar.

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

### 5.8 Frais de port offert par produit — dropshipping (2026-06, remplacé par §5.14)

> **Obsolète depuis Lot L3 (2026-07).** Les colonnes `pko_free_shipping`, `pko_quote_only` et `pko_logistics_class` ont été remplacées par `pko_port_mode`. Voir §5.14.

Le cas d'usage (fournisseur expédie directement, port inclus dans le prix d'achat) est toujours supporté via `pko_port_mode = 'free'` (anciennement `pko_free_shipping = true`).

### 5.9 Refonte frais de port 2026 — fondation (Lot L1, colonnes produit mises à jour en L3)

### 5.8bis Nettoyage L1 — table-rate hors admin, Colissimo off, seuil franco unifié

**Décisions actées au lot L1 (2026-07-29) — aucun impact sur les calculs, retrait du mort-bois uniquement.**

#### Table-rate Lunar hors admin

`Lunar\Shipping\ShippingPlugin::make()` a été **retiré** du panel Filament dans `AppServiceProvider`. Les entrées « Méthodes d'expédition », « Zones d'expédition » et « Listes d'exclusion » ont disparu du menu Expédition.

- **Raison** : `lunar_customer_group_shipping_method` est vide → le `ShippingRateResolver` du package rejette toutes les méthodes seedées. Aucune option ne sort au checkout. L'UI n'exposait que de la confusion.
- **Réactivation** : rajouter `->plugin(ShippingPlugin::make())` dans `AppServiceProvider` + peupler `lunar_customer_group_shipping_method` via `$method->scheduleCustomerGroup($groups)`.
- **Tables/migrations** : conservées (pas de `composer remove`, pas de rollback de migration). Le package `lunarphp/table-rate-shipping` reste dans `composer.json`.

#### Colissimo mis en veille

- **En DB** : migration `2026_07_29_100000_disable_colissimo_carrier_services` → `enabled=0` sur tous les services `colissimo` dans `pko_carrier_services`. `ShippingCalculator` interroge `CarrierServiceRepository::enabledFor('colissimo')` → vide → aucune option `colissimo.*` ne sort du manifest.
- **En admin** : `ColissimoConfig::shouldRegisterNavigation()` retourne `false` → page absente du menu Transporteurs. La page reste accessible par URL pour un opérateur qui en connaît l'adresse.
- **Réactivation** : `enabled=1` en DB + `shouldRegisterNavigation(): bool { return true; }` dans `ColissimoConfig`.
- **Package** : `packages/pko/shipping-colissimo/` conservé intégralement. `ColissimoModifier` supprimé en L4 — le client SOAP reste pour la création d'étiquettes post-paiement.

#### Source unique du seuil franco

Trois sources concurrentes existaient pour le seuil de livraison offerte. Résolution :

| Source | État après L1 | Rôle restant |
|---|---|---|
| `config('shipping.franco.threshold_ht_cents')` | **Fallback** — défaut 50 000 ¢ (= 500 € HT) | Utilisé quand aucune valeur DB présente |
| `Setting::get('shipping.franco.threshold_cents')` | **Source principale** depuis L2 | Lue via `ShippingSettings::thresholdCents()` |
| `Setting::get('shipping.free_threshold_cents')` | Champ supprimé de `StorefrontSettings` | Obsolète — ne pas utiliser |
| `config('storefront.shipping.free_threshold_cents')` | Inchangé en config | Plus utilisé pour l'affichage |

- Depuis L2, `FrancoModifier`, le bandeau panier et `ShippingOptions` lisent tous via **`ShippingSettings::thresholdCents()`** (résolution DB → config → 50 000).
- Le seuil a été porté à **500 € HT** (décision actée) en changeant le défaut dans `config/shipping.php`. Variable d'env : `FRANCO_THRESHOLD_HT_CENTS`.
- La valeur DB (`shipping.franco.threshold_cents`) gagne sur la config si elle existe.

**Data-model produit** — colonnes sur `lunar_products` après Lot L3 :

| Colonne | Type | Défaut | Rôle |
|---|---|---|---|
| `pko_port_mode` | `enum('inherit','standard','flat','free','quote')` | `'inherit'` | Mode de facturation du port (L3, remplace 3 colonnes L1) |
| `pko_franco_eligible` | `boolean` | `true` | Éligibilité au franco (false = exclu ; override possible) |
| `pko_transport_price_cents` | `int unsigned nullable` | `null` | Prix transport forfaitaire (mode `flat` uniquement) |
| `pko_supplier_id` | `bigint unsigned nullable FK` | `null` | Lien vers `pko_suppliers` (nullOnDelete) |

> **Colonnes supprimées en L3** : `pko_logistics_class`, `pko_free_shipping`, `pko_quote_only`. Migration de conversion `2026_07_29_100100`.

**Nouvelles tables** :

- `pko_suppliers` — fournisseurs (name, bl_neutre, lead_time_min/max_days, notes). Modèle `Pko\ShippingCommon\Models\Supplier`.
- `pko_shipping_surcharges` — suppléments transport (code, label, amount_cents, mode enum auto|quote|rebill, rule JSON, enabled). Modèle `Pko\ShippingCommon\Models\ShippingSurcharge`.

**Description longue sur les services** — colonne `description` (text nullable) ajoutée à `pko_carrier_services` (migration `2026_06_26_100200`). Exposée dans `CarrierService::$fillable`.

**Grille Chronopost 3 services** — data-migration `2026_06_26_120000` remplace l'ancienne grille (1 grille partagée `service_code=null`) par 3 services avec grilles individuelles (prix en cents HT) :

| max_kg | chrono_relais | chrono13 | chrono10 |
|---|---|---|---|
| 2  | 1490 | 1890 | 2490 |
| 5  | 1790 | 2290 | 2890 |
| 10 | 2290 | 2790 | 3490 |
| 20 | 3290 | 3990 | 4990 |
| 30 | *(absent → masqué >20 kg)* | 5490 | 6990 |

**Correctif `LivePricingResolver::resolveFromGrid()`** — ancienne version appliquait un seul prix à tous les services (`forCarrier($carrier)` sans service_code). Nouvelle version boucle sur les services activés et résout le prix via `forCarrier($carrier, $service['code'])` pour chaque service individuellement. Préférence : bracket service-spécifique > bracket null. Service sans bracket couvrant le poids → masqué silencieusement (pas de `QuoteResponse` injecté). Rétro-compat garantie : grille 100% null (Colissimo) continue de fonctionner — les brackets null jouent le rôle de fallback partagé.

**Colissimo** : grille inchangée (null service_code, prix partagé entre DOM et DOS). Aucune donnée migrée côté Colissimo.

### 5.10 Franco de port — paramètres et ShippingCalculator (L2 → L4)

> **L4 (2026-07)** : `FrancoModifier` et `FreeShippingModifier` supprimés. La logique franco est désormais dans `Pko\ShippingCommon\Pricing\ShippingCalculator` (étape 5 du pipeline interne). Un seul `UnifiedShippingModifier` subsiste dans le pipeline Lunar.

**Source unique de configuration** : `Pko\ShippingCommon\Settings\ShippingSettings` — ne jamais lire `config('shipping.franco.*')` ou `Setting::get()` directement dans les consommateurs.

#### Paramètres DB (page Admin → Expédition → Paramètres)

| Clé DB (`pko_storefront_settings`) | Type | Défaut | Rôle |
|---|---|---|---|
| `shipping.franco.threshold_cents` | `int` | 50 000 (= 500 € HT) | Seuil de déclenchement franco |
| `shipping.franco.services` | `array<string>` | `['chrono13']` | Codes nus des services couverts |
| `shipping.franco.basis` | `string` | `'eligible_only'` | Base de calcul du total |
| `shipping.tax.price_base` | `string` | `'ht'` | Nature des prix de grille |
| `shipping.tax.display` | `string` | `'both'` | Affichage HT/TTC au checkout |

La valeur DB gagne sur la config `.env`/`config/shipping.php`. La config reste le fallback (rétro-compat `FRANCO_THRESHOLD_HT_CENTS`).

#### Helpers ShippingSettings (résolution DB → config → défaut)

| Méthode | Retour | Résolution |
|---|---|---|
| `ShippingSettings::thresholdCents()` | `int` | DB `threshold_cents` → `config('shipping.franco.threshold_ht_cents')` → 50 000 |
| `ShippingSettings::francoServices()` | `list<string>` | DB `services` → `['chrono13']` |
| `ShippingSettings::francoBasis()` | `string` | DB `basis` → `'eligible_only'` |
| `ShippingSettings::taxPriceBase()` | `string` | DB `tax.price_base` → `config('shipping.tax.price_base')` → `'ht'` |
| `ShippingSettings::taxDisplay()` | `string` | DB `tax.display` → `config('shipping.tax.display')` → `'both'` |

#### Logique franco (ShippingCalculator — étape 5)

**Base `eligible_only`** (défaut) : le sous-total des lignes franco-éligibles doit atteindre le seuil ET aucune ligne n'est exclue. **Base `cart_total`** : toutes les lignes comptent dans le total, aucune ligne n'est bloquante.

**Éligibilité d'une ligne** (conditions cumulatives en mode `eligible_only`) :
1. `product.pko_franco_eligible === true`
2. `PortModeResolver::resolve($product) !== 'quote'`

**Services** : le calculator boucle sur `ShippingSettings::francoServices()` et annule le `gridPriceCents` de chaque `CalculatedShippingOption` dont `serviceCode` est dans la liste. Le forfait `flatPriceCents` et les suppléments ne sont **pas** annulés par le franco.

**Helpers WeightCalculator** :
- `WeightCalculator::francoEligibleSubtotalHt(Cart): int` — somme HT (cents) des lignes éligibles.
- `WeightCalculator::cartHasFrancoExcludedLine(Cart): bool` — true si ≥ 1 ligne non éligible.
- `WeightCalculator::cartSubtotalHt(Cart): int` — somme HT (cents) de TOUTES les lignes (basis `cart_total`).
- `WeightCalculator::fromLines(Collection): float` — poids d'un sous-ensemble de lignes pré-filtré (ajouté en L4 pour les lignes standard uniquement).

**Constante** : `UnifiedShippingModifier::CHRONO13_IDENTIFIER = 'chronopost.chrono13'` (rétro-compat `ShippingOptions::mount()`).

### 5.11 Composant `ShippingOptions` — déployé en checkout (Lot L5)

Composant unique partagé panier **et** checkout. Le partial `partials/checkout/shipping_option.blade.php` a été supprimé en L5.

#### Montage en checkout

Dans `livewire/checkout-page.blade.php`, quand `$currentStep == $steps['shipping_option']` :

```html
<livewire:components.shipping-options
    wire:key="shipping-options-{{ $cart->shippingAddress?->updated_at?->timestamp ?? 0 }}" />
```

Le `wire:key` force un remontage propre si l'utilisateur revient modifier l'adresse. Quand l'étape est passée (`$currentStep > $steps['shipping_option']`), une carte résumé (option + prix ou « Offert ») avec bouton « Modifier » remplace le composant.

#### Avancement d'étape checkout

`ShippingOptions::save()` → `CartSession::setShippingOption()` → `dispatch('selectedShippingOption')` → `CheckoutPage::onShippingOptionSelected()` → `refreshCart()` + `determineCheckoutStep()` → `currentStep = shipping_option + 1`.

#### Sélection par défaut

`mount()` présélectionne `chronopost.chrono13` si aucune option n'est déjà enregistrée. Fallback : première option disponible si `chrono13` absent du manifest.

#### Cartes de sélection (3 modes)

La vue `livewire/components/shipping-options.blade.php` affiche chaque option comme une carte radio avec libellé long, description et prix HT/TTC (ou « Offert »).

Mapping statique dans `ShippingOptions::getServiceLabelsProperty()` :
| Identifier | Libellé | Description |
|---|---|---|
| `chronopost.chrono_relais` | Livraison économique — Chrono Relais | Point relais Pickup, jusqu'à 20 kg. |
| `chronopost.chrono13` | Livraison standard — Chrono 13 | Livraison le lendemain avant 13h. |
| `chronopost.chrono10` | Livraison express — Chrono 10 | Le lendemain avant 10h, selon éligibilité code postal. |

#### Récap ventilé

Quand `hasVentilatedRecap` est vrai (`flat_price_cents > 0` OU `surcharge_cents > 0` OU options sentinel), un tableau de ventilation s'affiche sous les cartes :

| Ligne | Source | Condition |
|---|---|---|
| Transport standard | `meta['grid_price_cents']` | toujours |
| Forfait (par article flat) | `meta['flat_price_cents']`, lignes panier `pko_port_mode='flat'` | si > 0 |
| Supplément | `meta['surcharge_cents']` | si > 0 |
| Sur devis | options sentinel (`meta['quote'] === true`) | si présentes |
| **Total livraison HT** | somme | toujours |

Les meta sont injectés par `UnifiedShippingModifier` sur chaque `ShippingOption` Lunar.

#### Bandeaux dynamiques — source unique : `ShippingQuote::$banners`

> **Lot L5b (2026-07)** : les bandeaux ne sont plus recalculés dans le composant.
> `ShippingOptions::getBannersProperty()` appelle `app(ShippingCalculator::class)->calculate($cart)->banners`
> et délègue les 4 propriétés calculées (`isFrancoReached`, `francoRemainingCents`,
> `hasExcludedLines`, `hasMultipleSources`) aux résultats du quote.
>
> **Canal retenu** : résolution directe du `ShippingCalculator` depuis le composant
> (Option B) plutôt que stockage en meta du panier. Raison : zéro dirty-tracking,
> toujours frais, coût acceptable (grilles en DB, zéro appel SOAP pour les paniers
> sans lignes pondérées). Seul inconvénient : double calcul par page (modifier +
> composant), acceptable car non mesurable sur les grilles.
>
> **Divergence résolue** : `getHasMultipleSourcesProperty()` traitait `pko_supplier_id = 0`
> comme fournisseur (` !== null`) ; le calculator le traite comme Weklo (`null || 0`).
> La version du calculator (correcte) est désormais la seule.

| Bandeau | Type dans `$banners` | Message |
|---|---|---|
| Franco atteint (vert) | `franco_reached` | « Votre commande est éligible à la livraison standard offerte… » |
| Progression franco (ambre) | `franco_progress` + `remaining_cents` | « Plus que X € HT d'articles éligibles… » |
| Exclusion (info) | `excluded_lines` | « Certains produits volumineux… frais complémentaires. » |
| Multi-colis (info) | `multi_colis` | « Votre commande peut être expédiée en plusieurs colis… » |

Règle : **une seule fonction décide quels bandeaux s'affichent** — `ShippingCalculator::computeBanners()`.
Ne jamais recalculer ces conditions dans `ShippingOptions` ni dans la vue.

#### Badge disponibilité fiche produit

`livewire/product-page.blade.php` — priorité descendante :
1. `pko_port_mode = 'free'` → « Livraison offerte » (vert)
2. `variant->stock > 0` → « En stock — Expédition 24/48h » (vert)
3. `pko_supplier_id` renseigné → « Disponible sur commande fournisseur — {lead_min}–{lead_max} jours ouvrés » (ambre)
4. Sinon → « Livraison 24/48h » (neutre)

**Lignes panier** (`livewire/cart-page.blade.php`) — même logique via `CartPage::resolveAvailability()`. NE PAS afficher « dropshipping » côté client.

### 5.12 Suppléments transport (L5 → L4)

> **L4 (2026-07)** : `SurchargeModifier` supprimé. La logique de surcharge est désormais dans `ShippingCalculator` (étape 6 du pipeline). Voir §5.10 pour l'architecture globale.

**Modèle** : `pko_shipping_surcharges` (créé en L1) — colonnes `code`, `label`, `amount_cents`, `mode enum(auto|quote|rebill)`, `rule json`, `enabled`.

**Comportement dans ShippingCalculator — étape 6** :

| Mode | Comportement checkout |
|---|---|
| `auto` | Si la règle matche l'adresse → majore `autoSurchargeCents` de chaque `CalculatedShippingOption` non-sentinel. |
| `quote` | Si la règle matche → injecte une option sentinel (`isSentinel=true`, `identifier=surcharge.<code>`, `totalPrice=0`). |
| `rebill` | Ignoré au checkout (refacturation a posteriori hors flux panier). |

La surcharge auto se cumule **après** le franco : sur une commande Corse franco-éligible (≥ 500 € HT), chrono13 a `gridPriceCents=0` (franco) + `autoSurchargeCents=800` (Corse) → total 800 €.

**Évaluation des règles** (`rule` JSON) — dans `ShippingCalculator::matchesAddress()` :

| Clé | Exemple | Comportement |
|---|---|---|
| `match: always` | `{"match":"always"}` | Matche toujours |
| `type` | `{"type":"corse"}` | `ZoneResolver::isCorse()` sur le CP destinataire |
| `postcode_prefix` | `{"postcode_prefix":"20"}` | `str_starts_with(cp, prefix)` |

Extensible : ajouter un cas dans `ShippingCalculator::matchesAddress()`.

**Ouverture conditionnelle Corse** : `ShippingCalculator::shouldQuote()` accepte la Corse si `hasActiveCorseSurcharge()` retourne `true` (query `enabled=true AND mode IN ('auto','quote') AND (code=corse OR rule->type=corse OR rule->postcode_prefix=20)`).

Le mode `quote` est inclus depuis le lot L4 : une Corse couverte uniquement par un supplément `quote` doit ouvrir la zone pour que l'option sentinelle « sur devis » soit injectée. Avec la restriction précédente à `auto` seul, ce cas renvoyait un devis vide et la Corse était muette au checkout.

**Suppléments seedés** (`PkoShippingSurchargesSeeder`, idempotent via `updateOrCreate` sur `code`) — 9 suppléments de référence :

| code | label | mode | rule | amount_cents | enabled |
|---|---|---|---|---|---|
| `corse` | Supplément Corse | `auto` | `{"type":"corse"}` | 800 | ✅ |
| `zone_difficile` | Zone difficile d'accès | `auto` | `{"type":"zone_difficile"}` | 500 | ❌ |
| `livraison_samedi` | Livraison le samedi | `auto` | `{"match":"always"}` | 1500 | ❌ |
| `hors_normes` | Colis hors normes | `quote` | `{"type":"hors_normes"}` | `null` | ❌ |
| `manutention` | Manutention spéciale | `quote` | `{"type":"manutention"}` | `null` | ❌ |
| `transport_specifique` | Transport spécifique produit | `quote` | `{"type":"transport_specifique"}` | `null` | ❌ |
| `assurance` | Assurance marchandise | `rebill` | `null` | `null` | ✅ |
| `correction_adresse` | Correction d'adresse | `rebill` | `null` | `null` | ✅ |
| `retour_expediteur` | Retour à l'expéditeur | `rebill` | `null` | `null` | ✅ |

Seuls `corse` (auto) et les 3 `rebill` sont enabled par défaut. Une `rule` à `null` ne matche jamais (les rebill sont de toute façon exclus).

### 5.13 Base de taxe HT/TTC configurable + sélection point relais (Lot F4)

#### A) Base de taxe des frais de port — explicite et configurable

Les grilles transporteur sont stockées en **HT** (cents) — cf. §5.9. La base de taxe est désormais **explicite** via `ShippingSettings::taxPriceBase()` (clé DB `shipping.tax.price_base`, fallback `config('shipping.tax.price_base')`, env `SHIPPING_TAX_PRICE_BASE`, défaut `'ht'`) :

| `price_base` | Sens des prix de grille | Traitement dans `ShippingCalculator` | TVA |
|---|---|---|---|
| `'ht'` (défaut) | nets (hors taxe) | prix injecté tel quel + `TaxClass::getDefault()` | ajoutée par-dessus (comportement historique, inchangé) |
| `'ttc'` | TTC (taxe incluse) | reconverti en net `round(brut / (1 + taux))` puis `TaxClass::getDefault()` | correctement ventilée ; total payé = prix de grille |

**Principe clé** : `price_base` décrit la **nature du nombre stocké**, pas le taux de TVA appliqué. En mode `ttc`, on reconvertit la valeur en net via le **taux réel** de la TaxClass par défaut. Résultat : une grille `1200` en `ttc` @ 20 % et une grille `1000` en `ht` produisent **la même ligne** (net 1000, TVA 200, total 1200).

- Taux réel obtenu via `Pko\ShippingCommon\Support\ShippingTaxHelper::effectiveTaxRate()` (extrait en L4 depuis l'ancien `AbstractCarrierModifier`), zone-aware. Tolérant aux pannes : zone non résolue → pas de reconversion.
- `ShippingTaxHelper::grossToNet(int, float): int` — seule méthode de conversion, partagée par tous les carriers.
- Couvert par `tests/Unit/Shipping/CarrierTaxBaseTest` (reécrit en L4 pour cibler `ShippingTaxHelper`).

**Affichage panier et checkout** (`ShippingOptions` + vue) : chaque option montre HT **et** TTC (résolu via `ShippingSettings::taxDisplay()`, clé DB `shipping.tax.display`, fallback env `SHIPPING_TAX_DISPLAY`, valeurs `both` | `ht` | `ttc`, défaut `both`). Le TTC par option est calculé via le moteur de taxe Lunar (`Taxes::setShippingAddress()->setCurrency()->setPurchasable()->getBreakdown()`), donc zone-aware, avec fallback HT=TTC si la zone de taxe n'est pas résolue. Une option franco/offerte affiche « Offert ».

#### B) Sélection d'un point relais physique (Chrono Relais) — Lot L6

Quand le client choisit `chronopost.chrono_relais`, la sélection d'un **point relais** devient obligatoire avant de continuer.

**Abstraction** (`packages/pko/shipping-common`) :
- Contrat `Pko\ShippingCommon\Contracts\PickupPointProvider` — `search(string $postcode, string $countryCode = 'FR', ?string $serviceCode = null): array` (liste de `PickupPoint`).
- DTO neutre `Pko\ShippingCommon\Dto\PickupPoint` (id, name, address1, postcode, city, countryCode, distanceKm, latitude, longitude, openingHours) + `toArray()` / `fromArray()`. Coordonnées GPS optionnelles pour la carte.
- Implémentation fallback : `Pko\ShippingCommon\Pickup\ManualPickupPointProvider` (retourne `[]`), liée dans `ShippingCommonServiceProvider`. Si le package Chronopost n'est pas chargé, le front bascule sur une **saisie manuelle simplifiée**.

**Client SOAP point relais** (`packages/pko/shipping-chronopost`) :
- `Pko\ShippingChronopost\Services\PickupPointSoapClient` — appelle `recherchePointChronopostInter` sur `PointRelaisServiceWS` (WSDL officiel Chronopost). `serviceCode = null` (productCode vide = tous types de points). Timeout 8 s (`connection_timeout` pour le TCP handshake + `stream_context.http.timeout` pour la phase de lecture — les deux sont bornés à la même valeur). `WSDL_CACHE_BOTH`. En cas de timeout ou d'erreur SOAP, `PickupPointException` est levée et capturée par `ChronopostPickupPointProvider` → repli sur `[]`, jamais de 500 au checkout.
- `Pko\ShippingChronopost\Services\ChronopostPickupPointProvider` — implémente le contrat, cache les résultats 3 h par code postal, retourne `[]` sur erreur SOAP (jamais de rethrow), canal log `shipping-pickup`.
- Credentials : `secret('chronopost.account')` / `secret('chronopost.password')` (pko/lunar-secrets) avec fallback `config('chronopost.credentials.*')`.
- Binding : `ShippingChronopostServiceProvider::boot()` lie `PickupPointProvider → ChronopostPickupPointProvider` (boot garantit que ce binding écrase celui de `ShippingCommonServiceProvider::register()`).

**Carte OpenStreetMap / Leaflet** :
- Leaflet 1.9.4 chargé depuis CDN (`@push('scripts')` / `@push('styles')`) — aucune dépendance npm, aucune clé API.
- Layout côte-à-côte (liste scrollable gauche, carte droite sur `md+`). Synchronisation bidirectionnelle : clic marqueur → `$wire.set('pickupPointId')` → `updatedPickupPointId()` ; clic item liste → `selectPoint()` met à jour les icônes marqueurs.
- `wire:ignore` sur le conteneur carte pour éviter la destruction par Livewire lors des re-renders.
- Points sans lat/lon (GPS null) : liste uniquement, pas de marqueur.
- Icônes `divIcon` stylées avec classes Tailwind DS (`primary-400`/`primary-600`), aucun hex en dur.

**Front** (`App\Livewire\Components\ShippingOptions` + vue) :
- Bloc relais affiché uniquement si `requiresPickupPoint` (service = `chronopost.chrono_relais`).
- Champ code postal + bouton « Rechercher » → `searchPickupPoints()` interroge le provider (serviceCode = `null`, pas le slug interne). Résultats → liste radios + carte. Si aucun résultat → saisie manuelle simplifiée.
- `save()` : si Chrono Relais choisi sans point retenu → erreur `pickupPointId`. Le point est persisté dans `cart.meta['pickup_point']`.
- `FillOrderFromCart` (pipeline Lunar) copie l'intégralité de `cart.meta` → `order.meta` : la propagation du point relais est donc automatique, sans pipeline custom.

**Propagation vers l'expédition** (`CreateCarrierShipmentJob`) :
- `ShipmentRequest.pickupPointId` (optionnel, null = pas de relais) est alimenté depuis `order.meta['pickup_point']['id']`.
- `ChronopostClient::createShipment()` passe `recipientRelaisPointChronoId` dans `recipientValue` du payload SOAP.
- **Limitation connue** : le SDK `ladromelaboratoire/chronopostws` (`wsrecipientvalue`) ne définit pas de setter pour `recipientRelaisPointChronoId` → la valeur est passée dans le tableau mais **ignorée silencieusement** par `loadArray()`. Le code relais n'est pas transmis au WS Chronopost. Contournement pour lot 7 : remplacer `ChronopostClient::createShipment()` par un `SoapClient` brut pour Chrono Relais (même pattern que `PickupPointSoapClient`) ou fork du SDK.

Tests : `tests/Feature/Shipping/ShippingOptionsTest` (validation, persistance, purge) + `ChronopostPickupPointProviderTest` (succès, erreur → [], cache, points sans id) + `PickupPointSoapClientTest` (parse réponse unique/multiple, erreur API, SoapFault, credentials manquants).

### 5.7 Hors scope shipping

- Tracking webhook (polling ou push transporteur)
- Retour / annulation d'envoi (`cancelSkybill`)
- Livraison hors France métropolitaine (DOM, étranger) — Corse couverte via SurchargeModifier (L5)
- Sendcloud (alternative SaaS écartée pour coût)
- Transmission effective du code point relais au SOAP Chronopost (limitation SDK — cf. §5.13.B, lot 7)

### 5.14 Refonte modèle produit expédition — 6 réglages → 3 + héritage fournisseur (Lot L3, 2026-07)

#### Nouveau modèle `pko_port_mode`

Les trois colonnes d'origine (`pko_free_shipping`, `pko_quote_only`, `pko_logistics_class`) sont remplacées par **une seule colonne** `pko_port_mode enum('inherit','standard','flat','free','quote')` sur `lunar_products`.

| Mode | Signification | Franco |
|---|---|---|
| `inherit` | Délégué au fournisseur (cf. `port_inclus` ci-dessous) | selon résolution |
| `standard` | Tarif transporteur habituel (poids/dimensions) | éligible |
| `flat` | Prix forfaitaire fixe (`pko_transport_price_cents`) | exclu |
| `free` | Port inclus dans le prix d'achat (dropshipping) | exclu |
| `quote` | Commande sur devis, sans paiement immédiat | exclu |

Défaut colonne : `'inherit'` — tout nouveau produit hérite de la politique fournisseur.

#### Héritage fournisseur (`port_inclus`)

Nouvelle colonne `port_inclus enum('oui','non','cas_par_cas')` sur `pko_suppliers`. Résolution via `PortModeResolver::resolve(object $product): string` :

| `port_inclus` fournisseur | Mode résolu |
|---|---|
| `oui` | `free` |
| `non` | `standard` |
| `cas_par_cas` | `standard` (prudent — en attente de décision) |
| Pas de fournisseur | `standard` |

#### Franco — dérivation

Franco éligible dérivé = `(resolved_mode === 'standard')`.

- **Mode `inherit`** : `WeightCalculator::isFrancoEligible()` dérive toujours l'éligibilité du mode résolu (`PortModeResolver::resolve()`) — `pko_franco_eligible` en base est ignoré pour ce mode. Cela garantit que si le fournisseur change son `port_inclus`, l'éligibilité est immédiatement à jour sans toucher au produit.
- **Autres modes** : `pko_franco_eligible` reste persisté comme override possible ; le badge « Forcé manuellement » s'affiche dans la carte Expédition quand la valeur persistée diverge de la dérivée.

**Performance** : `PortModeResolver` maintient un cache statique `$supplierCache` (tableau en mémoire, indexé par `supplier_id`). Chaque fournisseur unique n'est requêté qu'une seule fois par process/request. Méthode `PortModeResolver::flushCache()` à appeler en `setUp()` des tests pour éviter la pollution inter-tests.

#### Filtre back-office « Port à trancher »

`PkoProductResource::getDefaultTable()` expose un filtre `port_a_trancher` : `pko_port_mode = 'inherit'` ET `supplier.port_inclus = 'cas_par_cas'`. Cette liste se vide au fil des décisions.

#### Migrations

| Fichier | Rôle |
|---|---|
| `2026_07_29_100000_add_pko_port_mode_to_lunar_products.php` | Ajoute `pko_port_mode` (défaut `inherit`) + `port_inclus` sur `pko_suppliers` |
| `2026_07_29_100100_migrate_pko_port_mode_data.php` | Convertit les 50 produits existants, supprime les 3 colonnes source |

Priorité de conversion : `quote_only=true` OU `logistics_class='C'` → `quote` ; `free_shipping=true` → `free` ; `supplier_id NOT NULL` → `inherit` ; sinon → `standard`.

#### Composants mis à jour

- `WeightCalculator` — utilise `PortModeResolver::resolve()` pour `fromCartTaxable`, `allLinesFreeShipping`, `isFrancoEligible`. Méthode `fromLines(Collection)` ajoutée en L4.
- `MarkQuoteOrderAwaitingQuote` — `WHERE pko_port_mode = 'quote'` (était `pko_quote_only = true`).
- `CheckoutPage::getIsQuoteOnlyCartProperty()` — idem.
- `product-page.blade.php` — badge livraison offerte sur `pko_port_mode = 'free'`.
- `LunarProductWriter::applyPortMode()` — mapping `logistics_class` A/B/C → `standard`/`inherit`/`quote`.
- `ShippingCalculator` (L4) — partitionne les lignes par `pko_port_mode` résolu : standard / flat / free / quote. Les lignes flat excluent leur poids du poids taxable (seules les lignes standard alimentent la grille), mais ajoutent `pko_transport_price_cents × quantité` à chaque option carrier.

### 5.15 Checkout branché sur ShippingOptions — récap ventilé et défaut Chrono 13 (Lot L5, 2026-07)

#### Ce qui change

| Avant (L4) | Après (L5) |
|---|---|
| Checkout utilisait `partials/checkout/shipping_option.blade.php` (radio brut, défaut = première option = Chrono Relais) | Checkout utilise `<livewire:components.shipping-options />` (cartes, défaut = Chrono 13) |
| Deux implémentations divergentes (panier + checkout) | Un seul composant partagé |
| Listener `selectedShippingOption → refreshCart()` (pas d'avancement d'étape) | Listener `selectedShippingOption → onShippingOptionSelected()` : `refreshCart()` + `determineCheckoutStep()` |
| Pas de récap ventilé | Récap ventilé si `flat_price_cents > 0` ou `surcharge_cents > 0` ou sentinelles |
| Bandeau franco atteint uniquement | Bandeau progression franco (montant restant ÉLIGIBLE, pas total) |

#### Fichiers modifiés

| Fichier | Modification |
|---|---|
| `app/Livewire/Components/ShippingOptions.php` | +`getFrancoRemainingCentsProperty`, `getSelectedOptionMetaProperty`, `getSentinelOptionsProperty`, `getHasVentilatedRecapProperty`, `getFlatLinesProperty`, `formatHtCents()` |
| `resources/views/livewire/components/shipping-options.blade.php` | Bandeau progression franco + section récap ventilé |
| `resources/views/livewire/checkout-page.blade.php` | Remplace `@include('partials.checkout.shipping_option')` par `<livewire:components.shipping-options />` + carte résumé quand étape passée |
| `app/Livewire/CheckoutPage.php` | Listener `selectedShippingOption → onShippingOptionSelected()`, suppression `saveShippingOption()` et `getShippingOptionsProperty()` |
| `resources/views/partials/checkout/shipping_option.blade.php` | **Supprimé** |
| `tests/Feature/Shipping/ShippingOptionsTest.php` | +3 tests récap ventilé, +2 tests bandeau franco progression |

#### Invariant franco

`getFrancoRemainingCentsProperty()` et `ShippingCalculator::computeBanners()` utilisent la **même base** (`francoEligibleSubtotalHt` ou `cartSubtotalHt` selon `ShippingSettings::francoBasis()`). Si l'un change, aligner l'autre.

---

### 5.13 Scission de commande — panier mixte (L7, 2026-07)

**Problème** : un panier peut contenir à la fois des produits `pko_port_mode='quote'` (transport inconnu) et des produits directement payables. Sans scission, l'opérateur est bloqué : il ne peut pas facturer les deux lignes ensemble.

**Solution** : à l'étape « Paiement » du checkout, le client choisit explicitement comment traiter son panier mixte.

#### Flux client

1. **Page panier** : bandeau d'information `CartPage::hasMixedCart` → "Votre panier contient N articles nécessitant un devis transport. Vous pouvez commander et payer les autres articles dès maintenant."
2. **Étape Paiement** : la vue `payment.blade.php` présente 2 options en radio :
   - **Commander et payer maintenant** (splitMode `split`, défaut) — les articles payables sont réglés immédiatement, les articles devis partent en commande `awaiting-quote` séparée.
   - **Tout regrouper en devis** (splitMode `quote_all`) — comportement existant §5.3, toute la commande part en `awaiting-quote`.
3. Bouton **Confirmer mon choix** → `CheckoutPage::confirmSplitChoice()` (action Livewire).
4. **Si mode `split`** :
   - `applySplit()` extrait les lignes devis du panier actif, les sérialise dans `cart.meta['split_pending']`, génère un `split_group` UUID, puis supprime ces lignes du cart (`CartSession::remove()`).
   - L'option de livraison est revalidée : si le franco a franchi un seuil ou si l'option n'existe plus pour le panier réduit, le client est renvoyé à l'étape livraison.
   - Le composant Stripe monte **uniquement sur le panier réduit** (total post-scission, jamais le total d'origine).
5. **Après paiement réussi** : `CheckoutPage::maybeCreateSplitQuoteOrder()` appelle `app(CreateSplitQuoteOrder::class)->execute($payableOrder, $splitPending, $splitGroup)`.
6. **Page de confirmation** : affiche les deux références si scission (payante + devis).

#### `CreateSplitQuoteOrder` — `app/Actions/CreateSplitQuoteOrder.php`

- Insère la commande devis via `DB::table('lunar_orders')` (contourne les casts Lunar — même pattern que les tests).
- Génère la référence via `app(OrderReferenceGeneratorInterface::class)->generate($order)` après insertion (ID connu).
- Copie les adresses de livraison et facturation depuis la commande payable.
- `shipping_total = 0` — l'opérateur renseigne le montant via **Envoyer lien de paiement** (flux existant §5.3).
- Lie les deux commandes via `meta` :
  - Commande devis  → `meta['split_from']` = ID commande payante (int), `meta['split_group']` = UUID
  - Commande payable → `meta['split_children']` = [ID commande devis] (int[])

#### Idempotence

- `applySplit()` : guard sur `cart.meta['split_pending']` non vide → no-op si déjà splité.
- `maybeCreateSplitQuoteOrder()` : guard `Order::where('meta->split_from', $orderId)->exists()` → interdit la création d'un second devis pour la même commande payable (double-submit proof).
- L'idempotence porte sur le tuple (commande payable, commande devis) — un seul devis par commande payante.

#### Décision — remises et codes promo

**Choix : refuser la scission si une remise ou un code promo est actif sur le panier.**

- `confirmSplitChoice()` vérifie `$cart->coupon_code !== null || ($cart->discount_total?->value ?? 0) > 0`. Si vrai et `splitMode='split'`, l'action renvoie une erreur de validation invitant le client à supprimer son code promo ou à choisir le mode `quote_all`.
- **Raison** : l'allocation proportionnelle d'une remise entre une commande payante et une commande devis est une opération non triviale (base de calcul ? répartition par ligne ? montant total ?) et risque de créer des écarts comptables. Le choix `quote_all` reste disponible : la remise s'applique normalement.
- **Impact opérateur** : pour les commandes en mode `split`, `discount_total = 0` sur la commande devis (aucune remise transférée). Si le client avait une remise et accepte le `quote_all`, la commande `awaiting-quote` la porte intégralement via le pipeline Lunar standard.

#### Back-office Filament

`OrderSplitBadgeExtension` (enregistrée sur `ManageOrder::class`) ajoute des badges lecture seule dans le header :
- Commande payante avec sibling devis → badge amber "Devis lié : REF-xxxx"
- Commande devis → badge info "Commande payante : REF-xxxx"

#### `isQuoteOnlyCart` vs `isMixedCart`

| Propriété | Condition | Comportement |
|---|---|---|
| `isQuoteOnlyCart` | **Toutes** les lignes physiques sont `pko_port_mode='quote'` | Flux devis seul (§5.3) |
| `isMixedCart` | Au moins une ligne quote ET au moins une non-quote | Flux scission (§5.13) |
| Ni l'un ni l'autre | Aucune ligne quote | Flux paiement standard |

**Piège** : avant L7, `isQuoteOnlyCart` retournait `true` dès qu'**une** ligne était quote, rendant le code de scission mort. Corrigé : filtre sur `type='physical'`, puis `->every()`.

#### Couverture tests

`tests/Feature/Shipping/OrderSplitTest.php` — 5 scénarios :
1. Panier 100 % payable → pas de split, pas de commande devis créée
2. Panier 100 % devis → flux quote seul inchangé (`awaiting-quote`)
3. Panier mixte + mode `split` → deux commandes créées, métadonnées liées correctes
4. Panier mixte + mode `quote_all` → une seule commande `awaiting-quote`
5. Double-submit idempotence → deux appels à `maybeCreateSplitQuoteOrder()` → toujours une seule commande devis

---

