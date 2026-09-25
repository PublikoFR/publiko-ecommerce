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

**Seed de base — SUPPRIMÉ (2026-07-31).** `PkoShippingSeeder` créait 1 zone `France métropolitaine`
et 3 méthodes (`pko-standard` ship-by par poids, `pko-pickup` collection, `pko-free` free-shipping
dès 500 €). Voir § 5.8ter pour le motif du retrait. **Ne pas le recréer** : le calcul des frais de
port passe intégralement par `UnifiedShippingModifier`.

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
   - `weklo` (pas de `pko_supplier_id`) → ~~dispatche `CreateCarrierShipmentJob`~~ enregistre un `CarrierShipment` `pending` ; l'étiquette est créée à la main (§5.26).
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
- **`AbstractCarrierConfigPage`** — rend, à partir de la `CarrierDefinition` : les deux tables CRUD (services, grille), puis le formulaire credentials (toggle env/DB) et le mode de tarification.
- **Tables `pko_carrier_services` et `pko_carrier_grids`** — data migration initiale (`2026_04_21_110100_seed_initial_carrier_data`) sème Chronopost (5 paliers, 3 services) et Colissimo (4 paliers, 2 services) depuis les anciennes valeurs config.
- **Ajouter un nouveau transporteur** : cf. [packages/transporters.md](packages/transporters.md) — ~80 lignes au total.

> **L4 (2026-07)** : `AbstractCarrierModifier` supprimé — remplacé par `ShippingCalculator` (cf. §5.10 / §5.12). Les sous-classes `ChronopostModifier` et `ColissimoModifier` sont supprimées. Seul `UnifiedShippingModifier` reste dans le pipeline Lunar.

#### 5.4bis Page config transporteur — tables CRUD (2026-07)

**Problème** : la page affichait d'abord deux Repeaters (services + grille) puis, en dessous, deux tableaux récapitulatifs en lecture seule des mêmes données → double affichage et scroll important.

**Décision** : la donnée n'est plus affichée qu'une fois, sous forme de **tables Filament CRUD placées en haut de page** ; le formulaire (credentials + mode de tarification) passe en dessous.

| Composant | Rôle |
|---|---|
| `Filament\Livewire\CarrierServicesTable` | Table CRUD `pko_carrier_services` d'un transporteur (create / edit / delete / réordonnancement, toggle `enabled` en ligne) |
| `Filament\Livewire\CarrierGridTable` | Table CRUD `pko_carrier_grids`, **groupée par service** (une sous-grille visuelle par service, triée par poids max) ; prix saisis en **euros**, stockés en cents ; service choisi via Select alimenté par les services du transporteur |
| `AbstractCarrierTable` | Base commune (Livewire + `InteractsWithTable`), vue `pko-shipping-common::livewire.carrier-table` |

La vue admin est étroite (sidebar) : les actions de ligne sont en **icônes seules** (`->iconButton()` + tooltip) et la colonne « Service » de la grille est portée par l'en-tête de groupe plutôt que par une colonne. La grille n'est pas réordonnable — `CarrierGridRepository` trie par `service_code` puis `max_kg`, la colonne `sort` n'y joue aucun rôle (elle reste utilisée par les services).

Pourquoi des composants Livewire dédiés plutôt que la table de la page : **une page Filament ne peut héberger qu'une seule table**. Les deux composants sont enregistrés dans `ShippingCommonServiceProvider::boot()` (`pko-shipping.carrier-services-table`, `pko-shipping.carrier-grid-table`) et embarqués via `@livewire(..., ['carrierCode' => ...])` — le pattern est donc réutilisable tel quel par tout nouveau transporteur qui hérite d'`AbstractCarrierConfigPage`.

**Invalidation du cache** : `AbstractCarrierConfigPage::save()` ne réécrit plus services/grille, donc le flush explicite des repositories n'y a plus lieu d'être. Il est remplacé par des listeners modèles (`CarrierService::saved/deleted`, `CarrierGridBracket::saved/deleted`) enregistrés dans le ServiceProvider : **toute** écriture (admin, migration, seeder, import tarifs publics Colissimo) invalide le cache du repository concerné.

**Paramètres d'expédition** : le champ « Services couverts par le franco » est un `Select` multiple recherchable, alimenté par `pko_carrier_services` et groupé par transporteur (au lieu d'un `TagsInput` en saisie libre, où l'admin ne savait pas quoi saisir). Seuls les **services actifs** sont proposés — un transporteur entièrement désactivé (Colissimo en veille) n'apparaît donc pas. Les codes déjà enregistrés mais absents de cette liste (service désactivé ou supprimé) sont conservés dans un groupe dédié pour ne pas être perdus silencieusement.

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

**Contrat QuickCost réel (2026-09-25, doc Web Services VL3.25.10.10 §2.7.4 + WSDL + appels sur le compte de test)** — corrige les hypothèses d'origine :

- **Opération `quickCostV3`** (la seule documentée). Même requête que `quickCost` v1, réponse sur-ensemble (ajoute `cap` = surcharges carburant) : bascule sans risque, vérifiée à l'identique sur le WS réel.
- **Requête** : `accountNumber`, `password`, `depCode`, `arrCode`, `weight`, `productCode`, `type=M`. `depCode`/`arrCode` = code postal, ou **code pays ISO-2** à l'international. Les anciens `depCountry`/`arrCountry` n'existent pas dans le type WSDL (ignorés par le serveur) → retirés.
- **`productCode` = code produit Chronopost** (`1`, `86`, `17`, `44`…), traduit depuis le slug interne par `CarrierProductCodeResolver` — comme pour les étiquettes (§5.21.B). Avant : le slug `chrono13` partait tel quel.
- **Réponse** : `amount` = **HT**, `amountTTC`, `amountTVA`, `zone`, `service[]` (suppléments : participation éco-responsable 0,22 € HT, samedi, Corse, domicile privé, douane…), `assurance`, `cap`. Ni `currency` ni `productCode` (EUR implicite). Les anciens champs `reservedAmount*`/`amountHT` n'existent pas → retirés. Les suppléments **ne sont pas additionnés** : à confirmer sur le compte réel s'ils sont inclus ou non dans `amount`.
- **Prix contrat marchand** (tarif négocié du compte appelant), **pas un prix public**.
- **Montant injecté = même nature que les grilles** : `ChronopostClient::quote()` injecte `priceCentsHT` en base `shipping.tax.price_base = ht` (défaut), `priceCentsTTC` en base `ttc`. Avant, le TTC était injecté puis `ShippingCalculator` ajoutait la TVA → **double TVA** latente dès le passage en live.
- **Cache live par base fiscale** : le `QuoteResponse` mis en cache 24 h par `LivePricingResolver` porte un montant HT ou TTC selon la base ; la clé inclut donc `price_base` et une version de format (`pko.shipping.{carrier}.quickcost.v2.{ht|ttc}.…`, constante `CACHE_FORMAT_VERSION`, à incrémenter si la nature du montant caché change). Basculer la base ne relit jamais un montant de l'autre nature, et les entrées v1 (TTC injecté) sont ignorées.
- **Montant nul = erreur** : le compte de test répond `errorCode=0` avec `amount=0.0` (aucun tarif associé). Un 0 deviendrait un port offert : `QuickCostException::amountNotFound()` → grille (`live_with_fallback`) ou service masqué (`live_only`). Conséquence : le mode live **n'est pas testable sur le compte de test**, seulement sur un compte contrat tarifé.
- **Erreurs 1–5** (§4.3.6 : système, paramètre manquant, mot de passe, produit incohérent avec la destination, tarif introuvable) → message explicite + code dans `QuickCostException::getCode()`.
- Tests : `tests/Unit/Shipping/QuickCostSoapClientTest` (réponse réaliste), `tests/Feature/Shipping/ChronopostLiveQuoteTest` (HT/TTC injecté, code produit, cache distinct par base fiscale).

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
2. **Méthodes schedulées contre les groupes clients** — `ShippingRateResolver` rejette toute rate dont `shippingMethod()->customerGroup($groups)->first()` est null. Sans entrée dans `lunar_customer_group_shipping_method` (via `$method->scheduleCustomerGroup($groups)`), **aucune** option ne sort, quel que soit le groupe du client.

Depuis 2026-07-31 c'est ce point 2 qui **maintient volontairement** les options table-rate hors du checkout (cf. § 5.8ter) — ce n'est plus un gotcha à corriger mais un invariant à préserver.

### 5.8ter Retrait des méthodes table-rate du checkout (2026-07-31)

**Symptôme** : trois options parasites s'affichaient au tunnel de commande à côté des services
Chronopost — « Livraison standard » (6,90 € TTC), « Retrait entrepôt » (0,00 €) et « Livraison
offerte » (0,00 €) — sans que personne ne sache d'où elles venaient.

**Cause** : ce sont les méthodes seedées par `PkoShippingSeeder` (`pko-standard`, `pko-pickup`,
`pko-free`). Le commentaire posé en L1 dans `AppServiceProvider` affirmait qu'« aucune option ne
sort au checkout (table vide → ShippingRateResolver rejette tout) » : **le postulat était faux**,
le seeder appelait `scheduleCustomerGroup($groups)` sur les trois méthodes. Sur toute base passée
par `make fresh`, le pivot était donc peuplé et les options remontaient. Retirer `ShippingPlugin`
du panel Filament n'avait supprimé que l'**UI**, pas le modifier : `Lunar\Shipping\ShippingModifier`
reste enregistré dans le manifest par le `ShippingServiceProvider` du package.

**Décision** : ces trois méthodes n'ont plus de rôle. Le port est calculé intégralement par
`UnifiedShippingModifier` / `ShippingCalculator`, le franco par `ShippingSettings::thresholdCents()`
(« Livraison offerte » en était un doublon codé en dur à 500 €), et aucune UI ne permet plus de les
éditer. Retrait :

- `PkoShippingSeeder` **supprimé** et retiré de `DatabaseSeeder`.
- Migration `2026_07_31_120000_retire_legacy_table_rate_shipping_methods` : `enabled=0` sur les
  trois codes **et** purge de leurs lignes dans `lunar_customer_group_shipping_method` (c'est le
  détachement qui les sort du manifest). Lignes conservées, pas de `delete()`.
- Le package `lunarphp/table-rate-shipping`, ses tables et ses resources Filament swappées
  (`PkoShippingMethodResource`…) restent en place — rien n'est désinstallé.

**Invariant à ne pas casser** : ne jamais re-seeder une méthode table-rate avec
`scheduleCustomerGroup()`, elle réapparaîtrait au checkout. Verrouillé par
`tests/Feature/SeedersTest::test_no_table_rate_shipping_method_is_seeded`.

**Réactivation** (si un jour on veut un vrai click & collect) : le faire comme un service à part
entière côté `pko_carrier_services` / `ShippingCalculator`, pas en ressuscitant le seeder — sinon
on retrouve une option non éditable et hors du calcul unifié.

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
| `shipping.franco.services` | `array<string>` | `['*']` | Codes nus des services couverts — `['*']` = tous (cf. §5.20) |
| `shipping.franco.basis` | `string` | `'eligible_only'` | Base de calcul du total |
| `shipping.tax.price_base` | `string` | `'ht'` | Nature des prix de grille |
| `shipping.tax.display` | `string` | `'both'` | Affichage HT/TTC au checkout |

La valeur DB gagne sur la config `.env`/`config/shipping.php`. La config reste le fallback (rétro-compat `FRANCO_THRESHOLD_HT_CENTS`).

#### Helpers ShippingSettings (résolution DB → config → défaut)

| Méthode | Retour | Résolution |
|---|---|---|
| `ShippingSettings::thresholdCents()` | `int` | DB `threshold_cents` → `config('shipping.franco.threshold_ht_cents')` → 50 000 |
| `ShippingSettings::francoServices()` | `list<string>` | DB `services` → `['*']` (tous les services) |
| `ShippingSettings::francoBasis()` | `string` | DB `basis` → `'eligible_only'` |
| `ShippingSettings::taxPriceBase()` | `string` | DB `tax.price_base` → `config('shipping.tax.price_base')` → `'ht'` |
| `ShippingSettings::taxDisplay()` | `string` | DB `tax.display` → `config('shipping.tax.display')` → `'both'` |

#### Logique franco (ShippingCalculator — étape 5)

**Base `eligible_only`** (défaut) : le sous-total des lignes franco-éligibles doit atteindre le seuil ET aucune ligne n'est exclue. **Base `cart_total`** : toutes les lignes comptent dans le total, aucune ligne n'est bloquante.

**Éligibilité d'une ligne** (conditions cumulatives en mode `eligible_only`) :
1. `product.pko_franco_eligible === true`
2. `PortModeResolver::resolve($product) !== 'quote'`

**Services** : le calculator annule le `gridPriceCents` de chaque `CalculatedShippingOption` couverte par `ShippingSettings::francoCovers()` (joker `*` ou code présent dans la liste). Le forfait `flatPriceCents` et les suppléments ne sont **pas** annulés par le franco.

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
| `chronopost.chrono10` | Livraison express — Chrono 10 | Le lendemain avant 10h, selon éligibilité code postal. **Hors contrat, service désactivé depuis le 2026-09-25 (§5.24).** |

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
- Contrat `Pko\ShippingCommon\Contracts\PickupPointProvider` — `search(string $postcode, string $countryCode = 'FR', ?string $serviceCode = null, ?string $city = null, ?int $weightGrams = null): array` (liste de `PickupPoint`) + `lastSearchError(): ?string` (cf. plus bas).
- DTO neutre `Pko\ShippingCommon\Dto\PickupPoint` (id, name, address1, postcode, city, countryCode, distanceKm, latitude, longitude, openingHours, openingSchedule, maxWeightKg) + `toArray()` / `fromArray()`, `hasFreeAccess()`, `acceptsWeightGrams()`. Coordonnées GPS optionnelles pour la carte. `toArray()` expose aussi `free_access` (dérivé, pour la vue).
- Implémentation fallback : `Pko\ShippingCommon\Pickup\ManualPickupPointProvider` (retourne `[]`), liée dans `ShippingCommonServiceProvider`. Si le package Chronopost n'est pas chargé, le front bascule sur une **saisie manuelle simplifiée**.

**Client SOAP point relais** (`packages/pko/shipping-chronopost`) :
- `Pko\ShippingChronopost\Services\PickupPointSoapClient` — appelle `recherchePointChronopostInter` sur `PointRelaisServiceWS`. `serviceCode = null` → `productCode = 86` (Chrono Relais 13H, champ obligatoire selon la doc WS VL3.25.10.10 ; il était vide jusqu'au 2026-09-25).

  **Endpoint (corrigé le 2026-07-31)** : `https://ws.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl`. L'ancienne valeur `recherchebt-wsdl/…` répondait **404** — le `SoapClient` échouait à la construction, donc avant même d'utiliser les identifiants. Aucune recherche de point relais n'avait jamais pu aboutir.

  **Paramètres obligatoires**, découverts empiriquement (le WS répond par des erreurs métier, pas par une validation de schéma) :

  | Paramètre | Valeur | Sans quoi |
  |---|---|---|
  | `type` | `P` (point relais Pickup) | erreur 300 « Il faut que le type ou le pudoType soient renseignés ». `A` (agence / bureau de poste) répond « pour l'instant non supporté » |
  | `service` | `L` | erreur 300 « service [] incorrect ». `L` et `T` renvoient le même jeu de points |
  | `city` | non vide | erreur 700 « The parameter named 'city' is required » |

  **Piège `city` > `zipCode`** : quand les deux divergent, **la ville gagne** — `zipCode=75001` + `city=Béziers` renvoie les points de Béziers. Conséquence : ne jamais transmettre une ville périmée après que le client a changé le code postal. `ShippingOptions::runPickupSearch()` ne passe la ville de l'adresse que si le code postal recherché est encore celui de l'adresse ; sinon `null`, et le client SOAP remplit alors `city` avec le **code postal lui-même** — valeur acceptée qui laisse la géolocalisation suivre le code postal (vérifié sur 34500 / 75001 / 69003 / 33000 / 59000 / 06000). La ville entre dans la clé de cache du provider, puisqu'elle change le résultat.

  **Compte de test** : Chronopost ne délivre pas de clé API en libre-service. Les comptes de démo publics `19869502` / `255562` et `68944403` / `501104` permettent d'exercer la recherche de points relais en dev (`CHRONOPOST_ACCOUNT` / `CHRONOPOST_PASSWORD` dans `.env`, non versionné). Ils ne valent que pour la consultation — pas pour créer de vraies étiquettes. Timeout 8 s (`connection_timeout` pour le TCP handshake + `stream_context.http.timeout` pour la phase de lecture — les deux sont bornés à la même valeur). `WSDL_CACHE_BOTH`. En cas de timeout ou d'erreur SOAP, `PickupPointException` est levée et capturée par `ChronopostPickupPointProvider` → repli sur `[]`, jamais de 500 au checkout.
- `Pko\ShippingChronopost\Services\ChronopostPickupPointProvider` — implémente le contrat, cache les résultats 3 h par code postal + ville (clé **versionnée** `chronopost_pickup:v2:…` — incrémenter `CACHE_FORMAT_VERSION` à chaque changement de forme des tableaux renvoyés par le client SOAP), retourne `[]` sur erreur SOAP (jamais de rethrow), canal log `shipping-pickup`.

  **Alignement sur la doc officielle (2026-09-25)** — doc WS VL3.25.10.10 §2.4.2 + exemple requête/réponse Chrono Relais 13H fournis par Chronopost :

  | Point | Avant | Maintenant |
  |---|---|---|
  | `productCode` | vide | `86` |
  | `shippingDate` (obligatoire, `JJ/MM/AAAA`) | vide | date du jour |
  | `weight` (grammes, 5 chiffres max) | vide | poids du panier (`WeightCalculator::fromCart()` × 1000) quand il est connu ; non transmis s'il est nul ou > 99 999 g |
  | `qualiteReponse` | ignoré | `0` (« résultat à ignorer ») → `PickupPointException` → `[]` + `lastSearchError()` renseigné. `1` (recherche sur le code postal) et `2` (sur l'adresse) sont acceptés |
  | `actif` | ignoré | point `actif=false` écarté |
  | `poidsMaxi` (toujours 20 kg) | ignoré | `maxWeightKg` ; le provider écarte les points dont la limite est dépassée |
  | Horaires | lus sur `listeHoraire->ouvertureMatin/fermetureApresMidi`, **champs inexistants** → `opening_hours = null` partout, jamais affichés | cf. ci-dessous |

  Appel réel au compte test le 2026-09-25 (33000, Béziers) : `productCode 86` + date + poids renvoient **exactement** le même jeu de points qu'avant — les découvertes empiriques ci-dessus (`type P`, `service L`, `city` prime sur `zipCode`) restent valables. **Le WS ne filtre pas sur `weight`** : 25 000 g renvoie les mêmes 20 points, tous à `poidsMaxi = 20`. D'où le filtre côté provider, appliqué **après** lecture du cache (le poids n'entre pas dans la clé de cache). Zéro point restant après ce filtre n'est pas une panne : `lastSearchError()` reste `null` et le front affiche « aucun point relais trouvé ».

  **Horaires** — structure réelle : un `listeHoraireOuverture` par jour **ouvert** (`jour` 1 = lundi … 7 = dimanche, `horairesAsString` « 08:15-12:00 12:00-17:00 », plages `listeHoraireOuverture{debut, fin}`), renvoyés dans le désordre (du samedi au lundi dans l'exemple). SoapClient rend un **objet** et non un tableau quand il n'y a qu'un élément, aux deux niveaux. `parseOpeningSchedule()` trie par jour, lit `horairesAsString` avec repli sur `debut`/`fin`, et produit :
  - `opening_schedule` : `list<{day, label, hours}>` (jour absent = fermé, rien d'inventé) ;
  - `opening_hours` : résumé lisible où les jours **consécutifs aux horaires identiques** sont regroupés — `Lun–Ven 08:15-12:00 12:00-17:00 · Sam 08:15-12:00` (point `8339S` de l'exemple). Plus compact qu'un jour par segment dans une liste de 20 points ;
  - aucun horaire du tout → `opening_schedule = []` = **accès libre** (la doc : « si aucune contrainte horaire n'est indiquée, il n'y en a pas, pour des consignes en libre service »). La vue affiche « Accès libre, sans horaires ». Horaires présents mais illisibles → `null` (rien d'affiché) : ne jamais annoncer un accès libre par défaut.

  **`type = P` inclut les consignes** (casiers automatiques, « Consigne Pickup … » dans les résultats) — `C` = relais sans consigne. Pas de changement de comportement : les consignes restent proposées. À savoir côté exploitation : en consigne le colis reste en instance **3 jours** (au lieu de 7 en relais) et **tous les gabarits ne sont pas éligibles** (à valider avec le commercial Chronopost avant de proposer du volumineux en relais). Passer à `C` écarterait les consignes si ces contraintes posent problème.
- Credentials : `secret('chronopost.account')` / `secret('chronopost.password')` (pko/lunar-secrets) avec fallback `config('chronopost.credentials.*')`.
- Binding : `ShippingChronopostServiceProvider::boot()` lie `PickupPointProvider → ChronopostPickupPointProvider` (boot garantit que ce binding écrase celui de `ShippingCommonServiceProvider::register()`).

**Carte OpenStreetMap / Leaflet** :
- **Le composant Alpine vit dans `resources/js/pickup-map.js`** (bundle Vite, fabrique globale `window.pickupMap`), pas en `x-data="{…}"` inline. Trois bugs l'imposaient, tous invisibles tant que la recherche ne renvoyait aucun point :
  1. Les gabarits JS contenaient des `class=\"…\"`. **En HTML, `\"` n'est pas une séquence d'échappement** : le backslash est littéral et le guillemet **referme l'attribut**. Tout le corps du composant était recraché en texte brut au milieu de la page.
  2. Leaflet était chargé via `@push('scripts')` / `@push('styles')`, or `resources/views/layouts/checkout.blade.php` ne déclarait **aucun `@stack`** — la CDN n'arrivait jamais sur la page de commande, la seule qui en a besoin. Les deux piles ont été ajoutées au layout, mais la carte n'en dépend plus.
  3. **Le hash SRI du JS Leaflet était corrompu sur sa fin** (`…NV/XN/WPeE=` au lieu de `…NV1lvTlZBo=`). Le navigateur bloquait donc le script **en silence** et la carte restait un rectangle gris. Un SRI faux ne se voit qu'à l'exécution : toujours le recalculer, jamais le recopier — `curl -s <url> | openssl dgst -sha256 -binary | openssl base64 -A`.
- **Fabrique globale, pas `Alpine.data()` sur `alpine:init`** : Alpine est embarqué dans le bundle Livewire (script classique en fin de `<body>`) alors que `pickup-map.js` part d'un `<script type="module">` différé du `@vite` en `<head>`. Selon le moment où Livewire démarre Alpine, `alpine:init` peut déjà avoir été émis — l'enregistrement arriverait trop tard et `x-data="pickupMap(…)"` ne résoudrait rien, sans erreur bruyante. Une fonction globale est résolue à l'évaluation de l'expression, ce qui supprime la course.
- Leaflet 1.9.4 chargé depuis CDN **à la demande** par `pickup-map.js` (injection `<link>`/`<script>` avec SRI, promesse partagée entre instances) — aucune dépendance npm, aucune clé API. **On attend la CSS autant que le JS** : sans la feuille appliquée, `.leaflet-container` n'a pas de mise en page et les tuiles partent hors cadre (encore un rectangle gris). Échec de chargement → `console.error('[pickup-map]', …)` et la liste sous la carte reste utilisable, c'est elle qui fait foi.
- `resources/js/**/*.js` est dans le `content` de `tailwind.config.js` : les classes des marqueurs (`bg-primary-400`, `ring-primary-300`…) sont bien scannées.
- Régression verrouillée par `ShippingOptionsTest::test_le_composant_carte_est_reference_pas_inline` (le HTML doit contenir `x-data="pickupMap(` et jamais `L.divIcon` / `fitBounds` / `L.tileLayer`).
- **Layout vertical (2026-07-31)** : carte pleine largeur en haut, liste scrollable en dessous. Remplace le côte-à-côte `md:` — sur une carte à demi-largeur les pins étaient illisibles. Synchronisation bidirectionnelle : clic marqueur → `$wire.set('pickupPointId')` → `updatedPickupPointId()` ; clic item liste → `selectPoint()` met à jour les icônes marqueurs.
- Cadrage par `fitBounds()` sur l'ensemble des points géolocalisés (`padding` 30 px, `maxZoom` 15). Le `setView()` sur le premier point à zoom fixe laissait une partie des pins hors écran.
- `wire:ignore` sur le conteneur carte pour éviter la destruction par Livewire lors des re-renders. **Corollaire** : le conteneur porte un `wire:key="pickup-map-{fingerprint}"` calculé sur les ids des points. Sans cette clé, `wire:ignore` empêchait aussi la mise à jour après une **nouvelle** recherche — l'ancienne carte et ses anciens pins restaient affichés.
- **Sélection = recentrage** : clic sur un item de la liste **ou** sur un pin → `selectPoint()` → `focusMarker()` ferme la bulle ouverte, `panTo()` le point choisi au centre, puis ouvre sa bulle. Les popups sont liées avec `autoPan: false`, sinon Leaflet recadre pour faire tenir la bulle et le point ne finit pas au centre.
- Items de la liste en `border-2`, **jamais `border` + `ring`** : le ring déborde de la boîte et se fait rogner par l'`overflow` du bloc scrollable (contour visiblement découpé sur les bords). Même traitement que les cartes de mode de livraison.
- Points sans lat/lon (GPS null) : liste uniquement, pas de marqueur.
- Icônes `divIcon` stylées avec classes Tailwind DS (`primary-400`/`primary-600`), aucun hex en dur.

**Front** (`App\Livewire\Components\ShippingOptions` + vue) :
- Bloc relais affiché uniquement si `requiresPickupPoint` (service = `chronopost.chrono_relais`).
- **Préchargement automatique (2026-07-31)** : la liste et la carte se chargent sans clic, sur le code postal de l'adresse de livraison — au `mount()` si un relais est déjà retenu, et via `updatedChosenOption()` dès que le client sélectionne Chrono Relais. Le champ code postal + « Rechercher » ne servent plus qu'à élargir/déplacer la zone. `autoSearchPickupPoints()` est silencieux (pas d'erreur de validation si le code postal est vide) et ne relance rien si une recherche a déjà eu lieu (`$pickupSearched`).
- Le champ code postal porte `wire:keydown.enter.prevent="searchPickupPoints"` : le bloc vit dans le `<form wire:submit="save">` de l'étape, valider au clavier soumettait sinon l'étape entière.
- Champ code postal + bouton « Rechercher » → `searchPickupPoints()` interroge le provider (serviceCode = `null`, pas le slug interne — le client SOAP pose `86` ; poids du panier en grammes transmis pour le filtre poidsMaxi). Résultats → carte + liste radios. Si aucun résultat → saisie manuelle simplifiée.

**Distinguer « zone non couverte » de « service en panne »** — `PickupPointProvider::lastSearchError(): ?string` :

`search()` est volontairement tolérant aux pannes et renvoie `[]` aussi bien pour une zone sans point relais que pour un WS injoignable ou des credentials Chronopost absents. Le front affichait donc **le même écran muet** dans les deux cas : le bouton « Rechercher » semblait ne rien faire (symptôme rapporté en dev, où `CHRONOPOST_ACCOUNT`/`CHRONOPOST_PASSWORD` sont vides — `PickupPointSoapClient` lève alors `missing account credentials` avant tout appel réseau).

- `lastSearchError()` renvoie le motif du dernier échec, ou `null` si la recherche a abouti (**y compris avec zéro résultat**). `ManualPickupPointProvider` renvoie `'no_provider_configured'` : aucune source branchée n'est pas « zéro point relais ».
- Le composant en dérive `$pickupServiceUnavailable` et la computed `pickupEmptyMessage` : « momentanément indisponible » vs « aucun point relais autour de ce code postal ». Motif technique jamais affiché au client, uniquement loggué (canal `shipping-pickup`).
- **Prérequis d'exploitation** : sans credentials Chronopost (Back-office → Transporteurs → Chronopost, ou `CHRONOPOST_ACCOUNT`/`CHRONOPOST_PASSWORD`), la recherche de points relais ne peut pas fonctionner — seule la saisie manuelle reste disponible.
- Couvert par `ShippingOptionsTest` : préchargement au changement d'option, préchargement au mount, message de panne, message de zone vide. **Tout test qui sélectionne Chrono Relais doit binder un `PickupPointProvider` factice** — sans double, la recherche automatique résout le provider Chronopost réel et part en SOAP.
- `save()` : si Chrono Relais choisi sans point retenu → erreur `pickupPointId`. Le point est persisté dans `cart.meta['pickup_point']`.
- `FillOrderFromCart` (pipeline Lunar) copie l'intégralité de `cart.meta` → `order.meta` : la propagation du point relais est donc automatique, sans pipeline custom.

**Propagation vers l'expédition** (`CreateCarrierShipmentJob`) — **corrigé le 2026-07-31, cf. §5.21** :
- `ShipmentRequest.pickupPointId` (optionnel, null = pas de relais) est alimenté depuis `order.meta['pickup_point']['id']` et **transmis à Chronopost dans `refValue.idRelais`** (clé SDK `idRelai`), cf. §5.24. *(Corrigé le 2026-09-25 : on affirmait ici qu'aucun champ « identifiant du point relais » n'existait — faux, `shippingMultiParcelV4` utilise le type `refValueV2` qui le porte.)*
- L'**adresse destinataire** est aussi celle du point relais, comme dans l'exemple officiel « Chrono RELAIS 13H ». `CreateCarrierShipmentJob::applyPickupPoint()` substitue l'adresse du point (nom du point en `company`, rue/CP/ville du point) tout en conservant nom, téléphone et e-mail du client — même approche que le module PrestaShop officiel, qui crée une `Address` dédiée au relais à la validation de commande.
- ❌ **Ancienne analyse erronée** : le champ `recipientRelaisPointChronoId` n'existe pas dans `recipientValue`. L'identifiant du point vit dans `refValue.idRelais` (§5.24).

Tests : `tests/Feature/Shipping/ShippingOptionsTest` (validation, persistance, purge, rendu horaires / accès libre) + `ChronopostPickupPointProviderTest` (succès, erreur → [], cache, cache versionné, filtre poidsMaxi, qualiteReponse=0, points sans id) + `PickupPointSoapClientTest` (parse réponse unique/multiple, erreur API, SoapFault, credentials manquants, horaires réels du point `8339S` construits d'après l'exemple officiel, jour unique en objet, sans horaires, qualiteReponse 0/1, point inactif, requête conforme à la doc).

### 5.7 Hors scope shipping

- Tracking **push** transporteur (webhook) — le **polling** est en revanche implémenté, cf. §5.6 (`shipping:poll-tracking`, horaire, API La Poste unifiée)
- Retour / annulation d'envoi (`cancelSkybill`)
- Livraison hors France métropolitaine (DOM, étranger) — Corse couverte via SurchargeModifier (L5)
- Sendcloud (alternative SaaS écartée pour coût)

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


### 5.16 Poids hors grille → transport sur devis (2026-07-31)

**Problème** : au-delà du dernier bracket de la grille (30 kg pour Chronopost), aucun service ne renvoie de tarif → `resolveWeightedOptions()` retournait un tableau vide. Le checkout n'affichait alors **aucune** option de livraison, et le paiement pouvait aboutir avec 0 € de port.

**Décision** : bascule explicite en *transport sur devis*.

| Couche | Comportement |
|---|---|
| `ShippingCalculator::resolveCarrierOptions()` | Si `weightKg > 0` et que les clients carrier ne renvoient aucun tarif → injecte une **option sentinelle** unique `quote.overweight` (`isSentinel=true`, prix 0). |
| `ShippingCalculator` (blockers) | Ajoute un blocker « Le poids de votre commande dépasse nos grilles tarifaires… » dès que **toutes** les options du manifest sont des sentinelles. |
| `ShippingQuote::isQuoteOnly()` | Nouveau helper : `true` si options non vides **et** toutes sentinelles. |
| `CheckoutPage::getIsQuoteOnlyCartProperty()` | Retourne `true` aussi quand `hasOnlyQuoteShippingOptions()` (manifest 100 % `meta['quote']`) → le panier part en commande `awaiting-quote`, sans paiement en ligne, comme un panier `pko_port_mode='quote'`. |

Constante : `ShippingCalculator::OVERWEIGHT_QUOTE_IDENTIFIER = 'quote.overweight'`.

> Le cas « flat-only » (poids taxable nul + forfaits) reste inchangé : il énumère les services à 0 € de grille et n'est donc jamais concerné par la sentinelle.

Tests : `tests/Unit/Shipping/ShippingCalculatorTest.php` — `test_poids_hors_grille_produit_une_sentinelle_sur_devis`, `test_poids_dans_la_grille_ne_produit_pas_de_sentinelle`.

### 5.17 Catalogue de test expédition — seeders (2026-07-31)

Deux seeders **additifs et idempotents** alimentent le catalogue de démo avec un produit par cas d'expédition. Ils sont enregistrés dans `DatabaseSeeder` après `PkoProductSeeder` et ne suppriment ni ne modifient aucune donnée existante — ils peuvent être rejoués sur une base vivante (`db:seed --class=…`), **sans `migrate:fresh`**.

| Seeder | Contenu |
|---|---|
| `PkoSupplierSeeder` | 3 fournisseurs couvrant les 3 valeurs de `port_inclus` : **SOMFY** (`non`, 5–10 j), *Fournisseur Port Inclus* (`oui`, BL neutre, 3–7 j), *Fournisseur Port À Trancher* (`cas_par_cas`, 7–15 j). `updateOrCreate` sur `name`. |
| `PkoShippingCasesProductSeeder` | 20 produits SKU `TX-01` → `TX-20`, valeurs **déterministes** (poids/prix/stock fixes, aucun `random_int`). Un produit dont le SKU existe déjà est ignoré. |

**Matrice couverte** (SKU → cas) :

| SKU | Cas testé |
|---|---|
| `TX-01`…`TX-06` | tranches de grille 2 / 10 / 20 kg, borne exacte 20 kg, > 20 kg (relais masqué), borne 30 kg |
| `TX-07` | 35 kg → hors grille → sentinelle « sur devis » (§5.16) |
| `TX-08` | 600 € HT → franco atteint à lui seul |
| `TX-09` | `pko_franco_eligible=false` → annule le franco du panier + bandeau exclusion |
| `TX-10`, `TX-11` | mode `flat` (forfaits 25 € / 120 €), poids exclu du poids taxable |
| `TX-12` | mode `flat` + franco forcé à `true` → badge « Forcé manuellement » |
| `TX-13` | mode `free` (port inclus), 30 kg exclus du taxable |
| `TX-14`, `TX-15` | mode `quote` (panier 100 % devis, et panier mixte avec `TX-15` + fournisseur) |
| `TX-16`…`TX-19` | mode `inherit` : `port_inclus` oui / non / cas_par_cas / sans fournisseur |
| `TX-20` | `purchasable='in_stock'` + stock 0 → rupture, non ajoutable au panier |

Pour retrouver ou purger ces produits : filtrer sur le préfixe SKU `TX-` (`PkoShippingCasesProductSeeder::skuPrefix()`).

### 5.18 Étape « mode de livraison » court-circuitée pour un panier 100 % devis (2026-07-31)

`ShippingOptions` valide `chosenOption` en `required`. Or un panier dont **toutes** les lignes sont `pko_port_mode='quote'` ne produit aucune option (le `ShippingCalculator` s'arrête avant la résolution carrier). Le client restait donc bloqué à l'étape livraison sur « Le champ chosen option est obligatoire », et le bouton **Demander un devis** (étape paiement) était inatteignable depuis le storefront.

`CheckoutPage::determineCheckoutStep()` saute désormais l'étape `shipping_option` quand `isQuoteOnlyCart` **et** que le manifest est vide.

> Le cas hors-grille (§5.16) n'est pas concerné : son manifest contient une sentinelle sélectionnable, qu'on laisse s'afficher pour expliquer l'absence de tarif. L'étape reste donc visible, puis le paiement bascule en devis.

Tests : `CheckoutQuoteInterceptionTest::test_quote_only_cart_skips_the_shipping_option_step` + `e2e/tests/expeditions/commande-sur-devis.spec.ts`.

### 5.19 Fixtures & garde-fous découverts en écrivant les tests (2026-07-31)

| Correctif | Détail |
|---|---|
| `WeightCalculator::isFrancoEligible()` | `pko_franco_eligible` est un tinyint **sans cast** sur `Lunar\Models\Product` : Eloquent renvoie `1`/`0`. La comparaison stricte `=== true` excluait du franco tout produit non-`inherit`, ce qui annulait le franco du panier entier (`cartHasFrancoExcludedLine`). Cast explicite en booléen. |
| `PkoCustomerSeeder` | Les comptes pro seedés n'étaient rattachés qu'à leur groupe métier (`installateurs`), et `ProAccess::denialReason()` exigeait alors **aussi** le groupe par défaut (`nouveau-client`) → connexion storefront refusée pour tous les comptes de démo, et suites E2E authentifiées bloquées. Le seeder attache désormais le groupe par défaut et pose `email_verified_at`. Le gate ne regarde plus les groupes du tout (cf. `docs/admin.md`, « Groupes clients et accès pro ») : seul `email_verified_at` reste nécessaire ici. |
| `DestructiveCommandGuard::isTestDatabase()` | La base de la stack Playwright (`pko_e2e`) n'était pas reconnue comme base de test → `migrate:fresh --seed` du `global-setup` bloqué par la garde anti-wipe, tous les runs E2E en échec. Exception explicite sur le nom `pko_e2e` (le verrou production reste absolu). |

### 5.20 Franco sur tous les services par défaut (2026-07-31)

**Règle métier confirmée** : au-delà du seuil, le port est offert **quel que soit le service choisi** — le client ne paie pas de supplément pour partir en express ou en point relais.

| Avant | Après |
|---|---|
| Défaut `shipping.franco.services = ['chrono13']` (relais et express restaient payants) | Défaut `['*']` — joker « tous les services » (`ShippingSettings::FRANCO_ALL_SERVICES`) |
| `in_array($opt->serviceCode, $francoServices)` dans `ShippingCalculator` | `ShippingSettings::francoCovers($francoServices, $opt->serviceCode)` |

La restriction à une liste reste possible : cocher des services dans **Admin → Expédition → Paramètres** limite le franco à ceux-ci. **Laisser le champ vide = tous les services** (la page convertit `[]` ⇄ `['*']`, le joker n'apparaît jamais comme option du select).

> **Piège liste restrictive** : si une liste explicite a été enregistrée (ex. `['chrono_relais', 'chrono13']`), tout nouveau service ajouté ultérieurement (ex. `chrono10`) ne sera **pas** couvert automatiquement. Le symptôme est : « le franco offre certains services mais pas les nouveaux ». Correctif : retourner dans **Admin → Expédition → Paramètres**, vider le champ « Services couverts par le franco » (= joker, recommandé), ou cocher explicitement le service manquant.

> Le joker ne touche ni les sentinelles « sur devis », ni les forfaits `flatPriceCents`, ni les suppléments `autoSurchargeCents` — un panier franco livré en Corse paie toujours son supplément (§5.12).

Tests : `ShippingCalculatorTest::test_franco_applique_sur_tous_les_services_par_defaut` + `test_franco_restreint_a_chrono13_quand_la_config_le_precise`, `ShippingCasesTest::test_scenario_05_franco_offre_tous_les_services`, `e2e/tests/expeditions/modes-livraison.spec.ts`.

---

### 5.21 Étiquettes réellement émises : code produit, relais, dimensions, impression en masse et bordereau (2026-07-31)

Audit de bout en bout de « qu'est-ce qui part réellement chez Chronopost à chaque commande », par comparaison avec le module PrestaShop officiel `chronopost` v7.5.6 (lecture seule, `~/webdev/projects/MDE Prestashop/modules/chronopost`).

#### A) La chaîne existante (rappel)

> **Remplacé le 2026-09-25 : plus aucune création automatique, cf. §5.26.**

`OrderShipmentObserver` (statut `paid` / `payment-received`) → `ShipmentSplitter` → `CreateCarrierShipmentJob` (queue redis, 5 tentatives) → `CarrierClient::createShipment()` → PDF dans `storage/app/labels/{order_id}/`, n° de suivi, commande en `dispatched`, e-mail client. Les lignes fournisseur (`supplier_direct`, `supplier_via_weklo`) sont enregistrées en `pending` **sans** appel transporteur.

#### B) Bloquant corrigé — `productCode` (aucune étiquette n'était créable)

Notre code de service interne (`chrono13`, `chrono_relais`, `chrono10`) était envoyé tel quel dans `skybillValue.productCode`, alors que Chronopost attend un code produit à part.

- Nouvelle colonne **`pko_carrier_services.carrier_product_code`** (migration `2026_07_31_100000`), éditable dans **Back-office → Expédition → Chronopost → Services** (champ « Code produit transporteur »).
- `Pko\ShippingCommon\Support\CarrierProductCodeResolver` (singleton, mémoïsé) : DB → `config('<carrier>.product_codes.<service>')` → repli sur le code de service lui-même.
- `ShipmentRequest::productCode()` est ce que les clients transporteurs envoient désormais. Le `serviceCode` interne reste porté par la ligne (grilles tarifaires, suivi, franco).
- **Colissimo n'est pas concerné** : `DOM` / `DOS` *sont* les codes produits. Colonne laissée à `NULL` → repli automatique.

| Service interne | Code produit Chronopost |
|---|---|
| `chrono13` | `01` *(était `1`, refusé — §5.24)* |
| `chrono10` | `02` *(hors contrat, désactivé)* |
| `chrono_relais` | `86` |
| `chrono18` | `16` |
| `chrono_classic` | `44` |

> Valeurs issues de `Chronopost::$carriersDefinitions` (module officiel, compte standard). Un compte « Petits pros » utilise une autre matrice (`9A`, `9B`, `9C`, `9F`) — d'où la colonne en base plutôt qu'une constante.

#### C) Dimensions et nombre de colis

`Pko\ShippingCommon\Support\ParcelDimensionsCalculator::fromOrder()` prend l'encombrement **maximal** des variantes de la commande (conversion mm/m/in → cm). Tant que les trois dimensions ne sont pas toutes connues, on retombe sur le carton par défaut `config('chronopost.packaging.default_dimensions_cm')` (30 × 20 × 15) — une seule dimension renseignée ne décrit pas un colis. `length` / `width` / `height` et `numberOfParcel` (`ShipmentRequest::$parcelCount`) partent maintenant dans le payload. La gestion de cartons multiples reste hors périmètre.

#### D) Impression en masse

Deux actions groupées sur **Expédition → Envois transporteurs** :

- **Télécharger les étiquettes (ZIP)** — `Pko\ShippingCommon\Support\LabelArchive` empaquette les PDF présents sur le disque (`ext-zip`). Choix d'une archive plutôt qu'un PDF concaténé : la fusion imposerait `setasign/fpdi` pour un besoin d'impression que tout lecteur PDF couvre déjà, et chaque étiquette reste au format exact renvoyé par le transporteur. Les envois sans étiquette sur disque sont ignorés silencieusement ; une sélection entièrement vide lève une `RuntimeException` rendue en notification.
- **Relancer la génération** — redispatch `CreateCarrierShipmentJob` pour les envois `weklo` non encore `created`. Les envois fournisseur sont ignorés (pas d'étiquette à notre nom).

#### E) Bordereau de remise (« bordereau du jour »)

Nouvelle page **Expédition → Bordereau de remise** (`DailyManifestPage`, slug `bordereau`) : liste des LT `created`, filtrable par date (défaut : aujourd'hui) et par transporteur, avec un bouton « Bordereau du jour » et une action groupée « Éditer le bordereau ».

Le PDF (`Support\ManifestPdf` + vue `pko-shipping-common::pdf.manifest`, dompdf) reprend la structure du bordereau officiel : bloc **émetteur** (`config('<carrier>.shipper')`, variables `SHIPPER_*`, repli `brand_name()`), **détail des envois** (n° de LT, commande, n° de compte, transporteur, produit, CP, ville, pays), **résumé** national / international / total, mention « Bien pris en charge N colis », puis les deux cases de signature (expéditeur / chauffeur). À imprimer en deux exemplaires.

> Le bordereau est construit **uniquement** depuis `pko_carrier_shipments` : c'est une preuve de prise en charge signée entre l'expéditeur et le chauffeur, elle ne transite par aucun web service. Le module PrestaShop procède de même (FPDF sur `chrono_lt_history`).

**Ciblage d'une journée par URL** : la page accepte `?date=YYYY-MM-DD` (`DailyManifestPage::requestedDate()`, repli sur aujourd'hui, format illisible ignoré). Le format natif `?tableFilters[created_on][date]=…` de Filament **ne fonctionne pas ici** : le défaut du filtre est appliqué au premier rendu et écrase la valeur d'URL. Corollaire d'implémentation : ce défaut doit être **évalué immédiatement** (`->default($this->requestedDate())`) et non passé en closure — en closure, Filament l'évalue dans un contexte où la page n'est pas résolue et le filtre part sur une date qui ne matche rien (table vide, sans erreur). Régression verrouillée par `DailyManifestTest::test_le_filtre_de_date_passe_en_url_isole_la_remise_visee`.

#### E bis) Raccourcis d'expédition sur la fiche commande

`OrderShipmentActionsExtension` (enregistrée sur `ManageOrder` dans `AppServiceProvider`) ajoute un groupe d'actions **Expédition** dès qu'un `CarrierShipment` existe pour la commande :

- **Télécharger l'étiquette** (une entrée par envoi, seulement si le PDF est présent sur le disque) ;
- **Envoi n° \<LT\>** → fiche `CarrierShipmentResource` ;
- **Bordereau du \<date\>** → page bordereau pré-filtrée sur la date de remise (`?date=`).

Le bordereau reste **journalier par nature** — il atteste d'une remise groupée au chauffeur — donc le raccourci pointe sur la journée de l'envoi, pas sur un bordereau propre à la commande. Jusqu'ici seul le chemin inverse existait (liste des envois → commande).

#### F) Ce qui reste à faire avant une mise en production

1. **Credentials réels** — `.env` porte le compte de démo `19869502`, valable pour la recherche de points relais uniquement. Aucune LT réelle ne peut être émise tant que le compte de production n'est pas saisi (Back-office → Expédition → Chronopost, ou `CHRONOPOST_ACCOUNT` / `CHRONOPOST_PASSWORD`). *(2026-08-31 : mail envoyé au commercial Chronopost pour obtenir le compte prod — en attente.)*
2. ~~Vérifier le format des codes produits sur le compte réel~~ — **conclusion de 2026-08-31 FAUSSE, corrigée le 2026-09-25 (§5.24)** : le WS exige deux caractères (`01`, pas `1`). Ancienne analyse conservée pour mémoire : Comparaison avec le module PrestaShop officiel (`MDE Prestashop/modules/chronopost/chronopost.php`, tableau `$carriersDefinitions`) : le service d'émission de LT (skybill) utilise exclusivement le champ `product_code` (`1`, `2`, `86`, `16`…), jamais `product_code_bal` (forme à deux chiffres, définie mais non lue ailleurs dans le module — code mort côté PrestaShop). Nos codes dans `packages/pko/shipping-chronopost/config/chronopost.php` (`chrono_relais=86`, `chrono13=1`, `chrono10=2`, `chrono18=16`, `chrono_classic=44`) correspondent exactement. Rien à corriger.
3. **Dimensions par variante** — tant que les variantes ne portent pas leurs dimensions, tous les colis partent au carton par défaut. Fonctionnalité inutilisée pour l'instant (catalogue sans variantes), mais `ParcelDimensionsCalculator` lit déjà les dimensions de variante si présentes (repli sur le carton par défaut sinon) : le jour où des variantes dimensionnées seront ajoutées, elles seront prises en compte automatiquement, sans changement de code.

#### G) Écarts assumés avec le module PrestaShop

| | PrestaShop | Weklo |
|---|---|---|
| Déclenchement | manuel : l'opérateur coche des commandes, saisit poids/dimensions/compte/samedi, puis « Print all waybills » | automatique à l'encaissement |
| Choix du compte, livraison le samedi, DLC Chronofresh, retours (`3T`/`4T`) | oui | non — hors périmètre |
| Étiquettes en masse | PDF concaténé | archive ZIP |
| Bordereau du jour | oui | oui (§E) |
| Historique LT | `chrono_lt_history` | `pko_carrier_shipments` |

Tests : `CarrierProductCodeResolverTest` (DB, config, repli, mémoïsation), `CreateCarrierShipmentJobTest` (code produit envoyé, substitution de l'adresse relais, meta relais hérité sans adresse, dimensions), `DailyManifestTest` (PDF généré, archive ZIP, sélection sans étiquette, rendu de la page, filtre de date par URL), `OrderShipmentActionsTest` (raccourcis présents sur une commande expédiée, absents sans envoi).

---

### 5.22 Point relais — l'adresse de la commande est celle du relais (2026-07-31)

**Symptôme** : commande passée en Chrono Relais, mais la fiche commande du back-office affichait l'adresse **du client** comme adresse de livraison. Le point choisi n'existait que dans `meta.pickup_point`.

**Cause** : `CreateOrderAddresses` (Lunar) recopie les adresses du panier vers la commande, et l'adresse du panier est celle du client. Rien ne portait la destination réelle du colis.

**Correctif** — pipeline `Pko\ShippingCommon\Pipelines\ApplyPickupPointAddress`, inséré dans `config/lunar/orders.php` **juste après** `CreateOrderAddresses` :

- si `meta.pickup_point` porte une `address1`, l'adresse de livraison de la commande devient celle du point (`company_name` = nom du point, rue/CP/ville du point, lignes 2 et 3 vidées) ;
- **nom, téléphone et e-mail du client sont conservés** — c'est ce qui permet au point relais de remettre le colis à la bonne personne ;
- l'adresse d'origine est sauvegardée dans `meta.delivery_address_original` (même convention que le module PrestaShop officiel), nécessaire au suivi et aux retours ;
- **idempotent** : un second passage (mise à jour d'un brouillon) ne prend pas l'adresse du relais pour l'originale.

Conséquence sur l'étiquette : `CreateCarrierShipmentJob::applyPickupPoint()` (§5.21.B) fait la même substitution au moment de créer la LT. Les deux se recouvrent volontairement — le job reste correct pour les commandes créées avant ce pipeline, et la substitution est idempotente.

> **Piège de test** : sauvegarder une `OrderAddress` déclenche l'observer de `lunarphp/table-rate-shipping`, qui résout une zone depuis le pays. Une adresse de fixture sans `country_id` fait lever un `TypeError` dans `PostcodeLookup::__construct()`, sans rapport apparent avec le code testé.

Tests : `tests/Feature/Shipping/PickupPointOrderAddressTest` (substitution, commande sans relais intouchée, idempotence).

---

### 5.23 Aucune étiquette générée en dev : il manquait un worker de queue (2026-07-31)

> **Remplacé le 2026-09-25 : plus aucune création automatique, cf. §5.26.**

**Symptôme** : commandes bien en `payment-received`, `shipping_option` correctement posée, mais **zéro ligne dans `pko_carrier_shipments`** — donc aucun n° de suivi, aucun raccourci « Expédition » sur la fiche commande, aucun bordereau possible.

**Diagnostic** : `OrderShipmentObserver` fonctionne — reproduit en conditions réelles, le job part bien. Mais `QUEUE_CONNECTION=redis` et **aucun service worker n'existait** dans `compose.yaml` : 675 jobs s'étaient accumulés dans `queues:default`, dont 6 `CreateCarrierShipmentJob`. Rien n'échoue, rien ne s'affiche, les jobs attendent simplement un consommateur qui n'existe pas.

> **Piège de diagnostic** : `redis-cli LLEN queues:default` depuis le conteneur redis répondait `0` et `KEYS 'queues:*'` ne renvoyait rien, ce qui laissait croire qu'aucun job n'était poussé. Laravel n'utilise pas forcément la base redis interrogée par défaut. Passer par l'application (`Redis::connection()->llen('queues:default')` en tinker) donne la vraie valeur.

**Correctifs**

1. **Service `scheduler`** ajouté à `compose.yaml` (`php artisan schedule:work`, même image que `app`). C'est l'équivalent Docker du cron `* * * * * php artisan schedule:run` de la production.

   Le choix de faire tourner la file *via le scheduler* plutôt qu'avec un worker résident n'est pas nouveau : il est déjà acté dans `routes/console.php`, parce que l'hébergement mutualisé cible n'a ni Supervisor ni systemd pour maintenir un `queue:work`. Le local se contentait de ne rien lancer du tout. Un service `scheduler` couvre donc d'un coup **les trois** tâches planifiées — worker de file, `shipping:poll-tracking` (horaire) et `ai-importer:run-scheduled` — et reste fidèle à la prod.

   Contrepartie assumée : jusqu'à une minute de latence avant qu'un job démarre. `make scheduler-logs` suit l'activité, `make schedule-list` liste les tâches et leur prochain passage, `make queue-status` donne la profondeur de file.

   > **`traefik_network` obligatoire sur ce service** (2026-09-17). C'est le scheduler qui dépile la file, donc qui envoie tous les mails en `ShouldQueue`. Le Mailpit partagé (`MAIL_HOST=mailpit`) n'est joignable que via `traefik_network` : rattaché au seul réseau `backend`, l'envoi échoue sur « getaddrinfo for mailpit failed » et aucun mail en file n'arrive en local.

   > **`init: true` obligatoire sur ce service** (2026-09-17). Les tâches `->runInBackground()` sont lancées via `sh -c '(…) &'` : le sous-shell orphelin est adopté par le PID 1 du conteneur. Sans init, ce PID 1 est `schedule:work` — PHP n'appelle jamais `wait()` sur des enfants adoptés, donc chaque tâche terminée laisse un zombie `[php] <defunct>` (≈4/min, 8 344 après 3 j 15 h, table des processus de l'hôte saturée). `init: true` injecte `tini` en PID 1, qui les libère. Ne pas retirer `runInBackground()` : il est nécessaire en prod (tick cron d'une minute, tâches longues), où le vrai init récupère les orphelins. Les autres conteneurs n'en ont pas besoin : `app` a `apache2` en PID 1, qui récupère ses enfants, et `mysql`/`redis` ne forkent pas de sous-processus orphelins.

   > Ne pas ajouter en plus un service `queue:work` résident : deux consommateurs sur la même file font doublon, et un worker résident garde le code en mémoire — il continuerait d'exécuter l'ancienne version d'un job jusqu'au redémarrage du conteneur, ce qui fait croire à un correctif sans effet.

2. **Commande de rattrapage** `php artisan shipping:backfill-shipments [--dry-run] [--limit=N]` : crée les envois manquants sur les commandes déjà payées. Elle couvre les commandes passées worker éteint, mais aussi le trou structurel décrit ci-dessous. Idempotente (les commandes portant déjà un envoi sont ignorées, et le job fait un `firstOrCreate`).

**Trou structurel assumé** : `OrderShipmentObserver` n'écoute que `updated` avec changement de statut. Un hook `created` serait inopérant — au moment où la commande est créée, ses adresses ne le sont pas encore (`CreateOrderAddresses` s'exécute après `FillOrderFromCart`), donc aucune `shipping_option` n'est lisible. Une commande **importée ou saisie directement dans un état payé** n'a donc pas d'étiquette : c'est `shipping:backfill-shipments` qui la rattrape.

> **Piège rencontré en écrivant la commande** : filtrer par `whereDoesntHave('addresses', fn ($q) => $q->whereNull('shipping_option'))` exclut **toutes** les commandes — l'adresse de facturation n'a jamais de `shipping_option`. Le filtre doit porter sur `type = 'shipping'`. Le test ne l'avait pas vu tant que son jeu de données ne comportait pas d'adresse de facturation ; elle y a été ajoutée pour verrouiller le cas.

**Fiche commande** : `OrderShipmentActionsExtension` affiche désormais le point relais retenu (nom + identifiant, adresse en infobulle) même quand aucune étiquette n'existe encore, et signale explicitement « Aucune étiquette générée » avec le renvoi vers `make queue-logs`. L'information n'était lisible que dans le dump brut de `meta` du bloc « Informations supplémentaires », généré automatiquement par Lunar et non modifiable sans toucher à `vendor/`.

Tests : `OrderShipmentObserverTest` (dispatch à la transition, pas de doublon, absence d'option, rattrapage et son idempotence), `OrderShipmentActionsTest` (point relais lisible sans étiquette).

---

### 5.24 Étiquettes `shippingMultiParcelV4` conformes à la doc officielle (2026-09-25)

Source : doc Chronopost Web Services **VL3.25.10.10** + exemples requête/réponse du contrat (reçus le 2026-09-25). Contrat : Chrono 13H `01`, Chrono Relais 13H `86`, Chrono Express `17`, Chrono Classic `44`. **Pas de Chrono 10 ni 18.**

**Ce qui empêchait toute étiquette Chrono 13** : `productCode = '1'` (repris du module PrestaShop). Le WS exige deux caractères (erreur 33) et la regex du SDK (`wsregex::__reg_ProductCodes`) refusait `1` **en silence** — le SDK était instancié avec `useExceptions = false` → `productCode` jamais posé → `RFLcheck()` faux → « RFL or SOAP error » sans détail. Au passage, `customerCivility` (obligatoire pour le `RFLcheck` du SDK) n'était pas envoyé non plus.

Correctifs (`ChronopostClient::createShipment()` / `buildLabelsData()`) :

- Migration `2026_09_25_100000_align_chronopost_product_codes_on_contract` (idempotente, ne touche pas un code saisi à la main) : `chrono13 → 01`, `chrono10 → 02`, `chrono10` et `chrono18` **désactivés** (lignes gardées pour les grilles et l'historique). `config/chronopost.php` `product_codes` aligné. Filet : un code à un chiffre encore en base est complété à gauche (`1` → `01`).
- **Point relais** : `refValue.idRelais` = `ShipmentRequest::$pickupPointId` pour les produits relais (`ChronopostClient::RELAY_PRODUCT_CODES = ['86']`). Clé SDK **`idRelai`** (sans « s »). Identifiant absent ou mal formé → `RuntimeException` explicite, pas d'étiquette vers une adresse sans point.
  - **Piège** : la doc et la regex SDK annoncent `[0-9]{4}[A-Za-z]` (ex. `3847U`), mais `recherchePointChronopostInter` renvoie aussi des points `999AA` (ex. `611BX`, 34 points sur 60 à Bordeaux/Paris/Lyon). Le SDK est donc contourné par `Pko\ShippingChronopost\Sdk\Shipment` / `Sdk\RefValue` (validation `^[0-9A-Z]{5}$`), injectés dans `chronopost::$shipment`. Vérifié en réel sur les deux formats.
  - Destinataire en relais : adresse du point, `recipientName` = nom du point, `recipientName2` = client, `recipientPhone` + `recipientMobilePhone` = téléphone client (SMS), `recipientEmail` client, `recipientType = 2`. Hors relais : raison sociale / contact, `recipientType = 1`.
- `shipperType = 1` (professionnel). `shipperCivility` / `customerCivility` depuis `config('chronopost.shipper.civility')` (env `SHIPPER_CIVILITY`, défaut `M`, valeurs `E|L|M`). `customerValue` = l'expéditeur (titulaire du contrat).
- `service` via `ChronopostClient::skybillService()` : `ShipmentRequest::$carrierService` (`0` semaine par défaut, `6` samedi). Le samedi n'est **pas** proposé au checkout — seul le kit de validation (§5.25) le demande.
- **SDK en `useExceptions = true`** : tout champ refusé lève avec son nom et sa regex, visible dans `pko_carrier_shipments.error_message`. Conséquence : le payload est **normalisé** au format du WS (`[a-zA-Z0-9 ]` — translittération ASCII, ponctuation → espace, adresse coupée en 2 × 38 caractères, CP alphanumérique, téléphones en chiffres `0…`/`+CC…`, entiers pour `accountNumber`/`subAccount`/montants/dimensions, `shipDate` en `Y-m-d`). Clés sans setter SDK retirées (`evtCode`, `as`) ; `version` omise (le SDK refuse « 2.0 », qui est le défaut du WS). Téléphones : un numéro déjà international est conservé tel quel (certains pays gardent un 0 significatif après l'indicatif, ex. Italie `+39 06…`) ; le 0 national n'est retiré que s'il est identifié sans ambiguïté — notation `(0)` après l'indicatif (`+33 (0)6…` → `+336…`) ou `+330…` pour la France — jamais par une règle générique. Téléphone destinataire invalide → erreur explicite ; e-mail destinataire absent → e-mail expéditeur.
- **Message d'erreur métier** : le SDK construit le sien par `"… $response->return->errorCode"`, ce que PHP n'interpole pas (« Object of class stdClass could not be converted to string »). `makeSdk()` injecte donc un `SoapClient` tracé dans la propriété privée `shippingSC`, et `describeFailure()` relit `errorCode` / `errorMessage` dans la réponse brute → ex. `erreur 38 — No routing found for country [FR] postCode [00000]`. Le mot de passe n'apparaît dans aucun message.
- **PDF déjà en base64** : `getReservedSkybillWithTypeAndMode` renvoie l'étiquette encodée (`JVBER…`). L'ancien code la ré-encodait → le `.pdf` stocké contenait du texte base64. Encodé seulement si la chaîne commence par `%PDF`.

**Vérification réelle** (compte test `19869502`, 2026-09-25) : Chrono 13H `01` → LT `XN450139241FR` ; Relais 13H `86` → `XS486816620FR` (point `611BX`) et `XS486816633FR` (point `7159X`). PDF d'une page, adresse du relais imprimée.

Tests : `tests/Unit/Shipping/ChronopostCreateShipmentTest` (payload 13H / relais, `idRelai` présent ou absent, types, civilités, normalisation, passage du `RFLcheck` SDK en mode exceptions, PDF non ré-encodé, erreur WS lisible sans mot de passe — SoapClient factice), `CarrierProductCodeResolverTest` (codes `01`/`02`, services hors contrat désactivés), `ShippingCasesTest` (Chrono 10 n'est plus proposé).

### 5.25 Validation prod Chronopost — kit d'étiquettes test (2026-09-25)

Chronopost ne délivre les identifiants de production qu'**après validation des requêtes ET des étiquettes test de chaque produit** du contrat, envoyées par mail au support Web Services (mail d'ouverture de compte, §1).

**Commande** : `make artisan CMD='chronopost:validation-kit'` (package `shipping-chronopost`, `Console\ChronopostValidationKitCommand`).

- Génère une étiquette par combinaison du contrat : Chrono 13H `01` service `0` et `6` (samedi), Chrono Relais 13H `86` service `0` et `6`. Le relais utilise un point **réel** obtenu par `recherchePointChronopostInter` (défaut `--zip=33000 --city=BORDEAUX`), avec la même substitution d'adresse que `CreateCarrierShipmentJob::applyPickupPoint()`.
- Utilise les **vrais** `ChronopostClient::createShipment()` et `PickupPointSoapClient::search()` : seul le transport change (`Validation\RecordingSoapClient`, qui consigne chaque échange — `createShipment()` enchaîne `shippingMultiParcelV4` puis `getReservedSkybillWithTypeAndMode`, et `__getLastRequest()` ne garderait que le second). Pas de payload recopié à la main : le kit montre exactement ce que la prod enverra.
- Identifiants : **compte test `19869502` / `255562` par défaut** ; `--account=… --password=… [--sub-account=…]` ou `--from-config` (coffre `secret()` puis `config('chronopost.credentials')`). Tout autre compte que le compte test est **refusé sans `--force`** (il produirait de vraies LT, à annuler).
- Expéditeur : `config('chronopost.shipper')` (env `SHIPPER_*`), complété champ par champ par des valeurs de test si vide — signalé dans le README. Destinataire hors relais : fictif (Paris 7e).
- Sortie `storage/app/chronopost-validation/<Y-m-d>/` (ignoré par git, `--output=` pour un autre dossier) : pour chaque étiquette la requête et la réponse SOAP brutes de chaque appel (`*-requete.xml` / `*-reponse.xml`, XML indenté, **mot de passe remplacé par `********`**, blobs base64 > 200 caractères tronqués — `Validation\SoapTraceSanitizer`), le PDF décodé (contrôle en-tête `%PDF`), et un `README.md` récapitulatif (produit, service, n° de LT, point relais, erreurs). Code retour non nul si une combinaison échoue ; l'erreur WS (`erreur NN — message`) est reportée dans le README.
- **Samedi** : le WS accepte `service = 6` pour un colis **remis le vendredi** (doc VL3.25.10.10 §3.1/§3.2) ; `shipDate` = date du jour. Lancer le kit un vendredi pour que les étiquettes samedi soient représentatives (le README indique le jour d'expédition). L'étiquette samedi porte la mention `SA13` au lieu de `13H`.
- **Aucun envoi externe** : Rom joint le dossier au mail du support Chronopost ; la commande n'envoie rien hormis les appels au WS.

Premier kit (compte test, vendredi 2026-09-25) : Chrono 13H `XN450178188FR` (semaine) / `XN450178191FR` (samedi), Relais 13H `XS486826613FR` / `XS486826627FR` (point `611BX`, Bordeaux). PDF de ~55 Ko, une page.

Tests : `tests/Feature/Shipping/ChronopostValidationKitCommandTest` (4 combinaisons envoyées, `idRelais` du point trouvé, PDF / README / traces écrits, mot de passe absent des XML, base64 tronqué, erreur WS consignée, refus d'un compte non test sans `--force`, `service = 6` transmis au skybill — SoapClient factice, aucun appel réseau).

### 5.26 Étiquette créée à la main depuis la fiche commande (2026-09-25)

**Décision** : l'étiquette transporteur n'est **plus jamais créée automatiquement** à l'encaissement. L'admin la crée quand le colis est prêt.

**Pourquoi** : créée d'office, l'étiquette partait avant la préparation (voire avant l'arrivée de la marchandise fournisseur en `supplier_via_weklo`), passait la commande en `dispatched` et envoyait au client l'e-mail d'expédition avec un suivi vide pendant des heures. Et un envoi `supplier_via_weklo` restait `pending` pour toujours : aucun mécanisme ne créait son étiquette « plus tard ».

**Fonctionnement** (`Pko\ShippingCommon\Shipping\ShipmentLabelService`) :
- À l'encaissement, `OrderShipmentObserver` appelle `recordPending()` : un `CarrierShipment` `pending` par origine (`weklo`, `supplier_direct`, `supplier_via_weklo`), **sans appel transporteur**. `shipping:backfill-shipments` fait de même pour les commandes déjà payées.
- Deux boutons **« Créer l'étiquette <transporteur> »** sur la fiche commande (`Pko\ShippingCommon\Filament\Support\CreateLabelActions`) : dans le menu **Actions** de l'en-tête (groupe Expédition, `OrderShipmentActionsExtension`) et dans la section **Livraison** (`OrderPageLayoutExtension`). Un bouton par origine dont l'étiquette manque (`originsAwaitingLabel()`, calcul sans écriture) ; libellé « Recréer » après un échec ; suffixe « stock » / « marchandise fournisseur » si la commande mélange les deux. `supplier_direct` n'a jamais de bouton (le fournisseur expédie).
- Le clic appelle `createLabel()` : exécution **synchrone** de `CreateCarrierShipmentJob::handle()` (même code qu'avant : appel transporteur, PDF, `dispatched`, e-mail client). Succès → notification avec le n° de suivi ; échec → `failed()` trace le motif sur l'envoi (`status=failed`, `error_message`) et une notification persistante l'affiche.
- Le job reste `ShouldQueue` : les actions « Relancer » de la liste des envois le mettent toujours en file.
- **Ouverture du PDF dans le navigateur**, sur le modèle des PDF Pennylane : route `pko.shipping.label.pdf` (`/admin/expedition/etiquettes/{shipment}/pdf`, `ShowCarrierLabelController`) protégée par la session staff **et** une URL signée temporaire de 12 h (`Pko\ShippingCommon\Support\CarrierLabelUrl`). Réponse `inline`, `Cache-Control: private, no-store`, `nosniff`, `no-referrer` ; le fichier reste sur le disque privé `local`. Après création, le bouton ouvre l'étiquette dans un nouvel onglet (`$livewire->js('window.open(…)')`) ; la notification garde un lien « Ouvrir l'étiquette » si le navigateur bloque l'onglet. Sur la fiche commande, « Télécharger l'étiquette » devient « Voir l'étiquette » (même lien, nouvel onglet) ; la liste des envois garde ses téléchargements (unitaire et ZIP).

- **Raccourci dans le bloc « vue d'ensemble »** (colonne latérale, sous la facture Pennylane, vue `filament.orders.order-overview`) : une ligne « Étiquette <transporteur> » par origine à notre nom (`OrderPageLayoutExtension::labelRows()`). Étiquette créée → n° de suivi + **Voir** (onglet) et **PDF** (téléchargement, `CarrierLabelUrl::for($s, download: true)`, paramètre `download` couvert par la signature). Étiquette absente ou en échec → « À créer » / « Création en échec » + **Créer**, qui monte l'action d'en-tête `create_label_{origin}` (`wire:click="mountAction(…)"`, même confirmation que le menu Actions).

Tests : `OrderShipmentObserverTest` (envoi `pending` sans job à l'encaissement et au rattrapage, idempotence, création manuelle, échec tracé, bouton du menu Actions — y compris l'ouverture de l'onglet — et bouton de la section Livraison via Livewire), `OrderAdminLayoutTest` (lien « Voir l'étiquette » signé en `target=_blank`, PDF `inline`, 403 sans signature ou lien altéré, redirection sans session staff). Faux transporteur partagé : `tests/Stubs/FakeCarrierClient.php` (hors classe de test, sinon Pint renomme `testCredentials()`).

