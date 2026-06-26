# Compte rendu — Refonte des frais de port Weklo (2026)

> Document de **planification**. Synthétise la demande client (3 cas + franco + classes
> logistiques + suppléments) et la traduit en modifications de code concrètes sur
> l'existant (`packages/pko/shipping-*`, storefront, back-office produit).
> À l'implémentation, les décisions tranchées migrent vers `docs/shipping.md`.

## 1. État de l'existant (point de départ)

| Brique | Fichier | Ce qu'elle fait aujourd'hui |
|---|---|---|
| Framework transporteurs | `pko/lunar-shipping-common` | `CarrierRegistry` + `AbstractCarrierModifier` + grilles/services en DB |
| Grille tarifaire | table `pko_carrier_grids` (`carrier_code`, `service_code`, `max_kg`, `price_cents`) | **déjà multi-service** au niveau schéma, mais voir §3 limite |
| Services | table `pko_carrier_services` | services activables par transporteur |
| Calcul poids | `WeightCalculator::fromCartTaxable()` | somme poids des lignes **non** `pko_free_shipping` |
| Franco actuel | `FreeShippingModifier` + flag produit `lunar_products.pko_free_shipping` | "livraison offerte" **uniquement si 100 % du panier est flaggé** (logique dropship, pas un franco au montant) |
| Zone | `ZoneResolver::isMetropole()` | France métro seule (exclut Corse `20*`, DOM) |
| Front panier | `app/Livewire/Components/ShippingOptions.php` + `resources/views/livewire/components/shipping-options.blade.php` | liste radio des options, pas de défaut, pas de messages |
| Résolution prix | `LivePricingResolver::resolveFromGrid()` | **applique un seul prix à tous les services** (cf. §3) |

**Ce qui n'existe pas du tout** : notion d'origine de stock (Weklo vs fournisseur), modèle
fournisseur / BL neutre, délais d'appro, classes logistiques A/B/C, franco au montant HT,
exclusions de franco par produit, suppléments transport, multi-expédition.

---

## 2. CAS 1 — Produits stockés chez Weklo (cœur de la demande)

### 2.1 Les 3 services = renommer/recâbler la grille Chronopost

Les 3 modes demandés correspondent exactement à 3 services Chronopost :

| Mode client | Service Chronopost | `service_code` proposé | Contrainte |
|---|---|---|---|
| Économique point relais | Chrono Relais (Pickup) | `chrono_relais` | ≤ 20 kg seulement |
| Standard (défaut) | Chrono 13 | `chrono13` | défaut sélectionné |
| Express premium | Chrono 10 | `chrono10` | reste payant même si franco |

**Action** : reseed `pko_carrier_services` (carrier `chronopost`) avec ces 3 services + leurs
libellés "client" (les phrases d'affichage conseillées). Stocker les descriptions longues
(point relais Pickup / livraison J+1 avant 13h / avant 10h) dans une colonne `description`
sur `pko_carrier_services` (à ajouter si absente).

### 2.2 Grille tarifaire 5 tranches × 3 services

Tranches (`max_kg`) : 2, 5, 10, 20, 30. Prix en **cents HT** :

| max_kg | chrono_relais | chrono13 | chrono10 |
|---|---|---|---|
| 2  | 1490 | 1890 | 2490 |
| 5  | 1790 | 2290 | 2890 |
| 10 | 2290 | 2790 | 3490 |
| 20 | 3290 | 3990 | 4990 |
| 30 | *(absent)* | 5490 | 6990 |

**Action** : data-migration qui purge la grille `chronopost` actuelle et insère 14 brackets
(`service_code` renseigné, pas `null`). L'absence de bracket `chrono_relais` à 30 kg fait
tomber le service au-delà de 20 kg → masquage automatique (cf. §2.3).

### 2.3 ⚠️ Correctif bloquant — résolution prix **par service**

`LivePricingResolver::resolveFromGrid()` (lignes 103-137) appelle
`$this->grids->forCarrier($carrier)` **sans service_code** → ne lit que les brackets
`service_code = null` et applique **le même prix à tous les services**. Idem
`fallbackQuoteFromGrid()` (l. 190). Avec la grille ci-dessus, les 3 services sortiraient au
même tarif. **Faux.**

**Action** : refondre `resolveFromGrid()` (et `fallbackQuoteFromGrid()`) pour boucler sur
les services activés et résoudre le bracket via `forCarrier($carrier, $service['code'])`,
en prenant le **premier bracket du service** dont `max_kg >= poids`. Si aucun bracket pour
ce service (cas Relais > 20 kg) → service **non injecté** → masqué côté panier sans code
spécial. `CarrierGridRepository::forCarrier()` supporte déjà le filtrage par service.

### 2.4 Franco de port 350 € HT (Chrono 13 uniquement)

Règles : franco sur **montant total HT du panier des lignes éligibles**, applicable **au
seul Chrono 13**, l'express et le relais restent payants.

**Action** : nouveau modifier `FrancoModifier` (dans `shipping-common`), enregistré **après**
les `AbstractCarrierModifier` (l'ordre d'ajout dans `ShippingModifiers` est déterminant) :

1. Calcule `sousTotalHT_eligible` = somme des lignes du panier **non exclues du franco**
   (cf. §4 flag `pko_franco_eligible`).
2. Si `>= 35000` cents ET aucune ligne du panier n'est exclue du franco → remplace dans le
   manifest l'option `chronopost.chrono13` par une option à **0 €** (label "Livraison
   standard offerte").
3. Ne touche jamais `chrono10` ni `chrono_relais`.

Tableau de vérité à coder :

| Situation | Comportement |
|---|---|
| Panier < 350 € HT | Relais / Standard / Express payants selon grille |
| Panier ≥ 350 € HT | Chrono 13 → 0 €, Relais & Express payants |
| Panier ≥ 350 € HT + choix Express | client paie le supplément Express (grille chrono10) |
| Au moins 1 produit non éligible | franco **non** appliqué, grille pleine sur tout |

### 2.5 Sélection par défaut = Chrono 13

**Action** : dans `ShippingOptions::mount()`, si aucune option déjà choisie, présélectionner
l'identifier `chronopost.chrono13` (fallback : première option). Aujourd'hui `chosenOption`
reste nul.

### 2.6 Affichage panier (storefront)

`shipping-options.blade.php` actuel = radio brut (prix + nom). À enrichir :

- 3 cartes : libellé long + délai + "Tarif selon le poids" / "Offerte dès 350 € HT" /
  "avec supplément".
- Bandeau franco dynamique : « Votre commande est éligible à la livraison standard offerte.
  Vous pouvez choisir une livraison express avec supplément. » (si seuil atteint).
- Bandeau exclusion : « Certains produits volumineux, spécifiques ou livrés dans des zones
  particulières peuvent faire l'objet de frais de transport complémentaires. » (si une ligne
  exclue est présente).
- Mention "€ HT" sur les tarifs (cf. §6 point ouvert taxe).

---

## 3. CAS 2 — Produit non stocké Weklo, dispo fournisseur

Besoin = afficher le produit comme « disponible sur commande fournisseur », délai « 5 à 10
jours ouvrés », **sans** le mot "dropshipping". Tarif transport = grille **Chrono 13**.
Routage interne (BL neutre → direct fournisseur ; sinon transit Weklo) = logique back-office.

**Nouveau modèle de données** :

- Table `pko_suppliers` (`name`, `bl_neutre` bool, `lead_time_min_days`, `lead_time_max_days`,
  `notes`). Resource Filament dédiée (groupe Expédition ou Catalogue).
- Lien produit→fournisseur : colonne `pko_supplier_id` nullable sur `lunar_products`
  (FK `pko_suppliers`).
- Origine de stock : dérivée. Si stock variante Weklo > 0 → "En stock Weklo" ; sinon si
  `pko_supplier_id` renseigné → "Sur commande fournisseur" + délai du fournisseur.
  Pas besoin d'un flag stocké redondant (source de vérité = stock Lunar + lien fournisseur).

**Checkout** : ces produits passent par la grille Chrono 13 standard (déjà couvert par le
modifier Chronopost). Le routage BL-neutre/transit est **post-commande** (back-office), pas
une règle de prix au panier — à matérialiser comme métadonnée d'expédition (cf. §5
multi-expédition), pas comme option de transporteur.

**Front** : badge disponibilité + délai sur fiche produit et ligne panier (« Disponible sur
commande fournisseur — Livraison estimée sous 5 à 10 jours ouvrés »).

---

## 4. CAS 3 — Commande mixte + classes logistiques + franco global

### 4.1 Franco calculé sur le total panier (pas ligne à ligne)

Déjà couvert par le `FrancoModifier` §2.4 : il somme le sous-total HT éligible du panier
entier. Confirmation : un produit stock Weklo 120 € + fournisseur 260 € = 380 € → franco
atteint (si les deux sont `pko_franco_eligible`).

### 4.2 Classes logistiques A / B / C

**Action** : colonne `pko_logistics_class` (enum `A|B|C`, défaut `A`) sur `lunar_products`.

| Classe | Règle franco | Transport | Donnée produit requise |
|---|---|---|---|
| A — standard | éligible | grille classique | défaut |
| B — fournisseur surcoût | éligible *si marge le permet*, sinon supplément affiché | grille + surcoût fournisseur | lien fournisseur + surcoût |
| C — volumineux/spécifique | **hors franco auto** | **prix transport par produit** + "sur devis" possible | `pko_transport_price_cents` (nullable), `pko_quote_only` (bool) |

- Classe C ⇒ `pko_franco_eligible = false` de fait + **prix transport éditable par produit**
  (colonne `pko_transport_price_cents`). Un `ProductTransportModifier` ajoute ce montant au
  manifest (ou remplace le calcul grille) quand une ligne classe C est présente.
- `pko_quote_only = true` ⇒ pas de prix au panier, mention « Transport sur devis » et blocage
  du paiement auto (à arbitrer §6).

### 4.3 Flag exclusion franco

**Action** : colonne `pko_franco_eligible` (bool, défaut `true`) sur `lunar_products`.
Mise à `false` pour : volumineux, longs, fragiles, palettes, volets roulants, coulisses
longues, menuiseries, hors normes, commandes spéciales. Pilote le `FrancoModifier` §2.4 et le
bandeau d'exclusion §2.6. (Classe C force `false`.)

### 4.4 Multi-expédition

Une commande peut générer 2 expéditions (Weklo + fournisseur). L'infra `CarrierShipment`
existe déjà (1 envoi = 1 ligne), mais la **logique de découpage** n'existe pas.

**Action** :
- Au post-paiement (`OrderShipmentObserver` / `CreateCarrierShipmentJob`), grouper les lignes
  par origine (stock Weklo vs fournisseur BL-neutre vs fournisseur transit) et créer **N
  `CarrierShipment`** au lieu d'un seul.
- Métadonnée par ligne d'origine : déduite du `pko_supplier_id` + `bl_neutre` du fournisseur.
- Front : messages panier « Votre commande peut être expédiée en plusieurs colis… » et
  « articles avec des disponibilités différentes » quand le panier mélange les origines.

> Périmètre à confirmer §6 : le découpage multi-expédition automatique est le morceau le plus
> lourd. Peut être livré en V2 (V1 = un seul envoi + mention informative au panier).

---

## 5. Suppléments transport (transverse)

Corse, zone difficile, hors normes, manutention, samedi, assurance, correction d'adresse,
retour expéditeur, transport spécifique.

**Action** :
- Table `pko_shipping_surcharges` (`code`, `label`, `amount_cents` nullable, `mode` enum
  `auto|quote|rebill`, `rule` JSON nullable). Resource Filament (groupe Expédition).
- `mode=auto` : appliqué si règle connue (ex. CP Corse `20xxx` → +X €). Un `SurchargeModifier`
  ajoute une `ShippingOption` ou ajuste le prix.
- `mode=quote` : affiche « transport sur devis », pas de prix auto.
- `mode=rebill` : refacturation a posteriori (ex. erreur d'adresse client) — back-office, hors
  flux panier.
- La Corse étant aujourd'hui exclue par `ZoneResolver::isMetropole`, ouvrir la zone Corse
  conditionnellement à un supplément (sinon aucune option ne sort pour `20xxx`).

---

## 6. Points à trancher (à valider avec le client / Rom)

1. **Taxe HT/TTC** : la grille est en € HT. Aujourd'hui les `ShippingOption` portent une
   `TaxClass` et le front affiche `formatted()` (TTC). Confirmer : stocker HT + laisser Lunar
   ajouter la TVA, et afficher "€ HT" au panier B2B ? Impact `ShippingOptions` + blade.
2. **Périmètre V1 vs V2** : le multi-expédition automatique (§4.4) et le routage BL-neutre
   sont lourds. Proposition : V1 = grille 3 services + franco + classes/exclusions +
   affichage ; V2 = multi-expédition réelle + suppléments avancés. À arbitrer.
3. **Colissimo / point relais** : la demande parle de "Chrono Relais" (Pickup). Aujourd'hui
   le relais est listé "hors scope" (`docs/shipping.md` §5.7). On l'intègre comme service
   Chronopost simple (grille statique) ou via l'API points relais ? V1 = grille statique
   sans sélection de point relais physique ?
4. **`pko_quote_only`** (classe C devis) : bloque-t-on le paiement en ligne, ou commande
   créée en "attente de devis transport" ?
5. **Classe B "si la marge le permet"** : quelle donnée de marge ? (prix d'achat fournisseur
   vs prix de vente). Nécessite de connaître le coût d'achat — disponible ?

---

## 7. Récap des modifications par couche

### Migrations (DB)
- `lunar_products` : `pko_logistics_class` (enum A/B/C), `pko_franco_eligible` (bool, def true),
  `pko_transport_price_cents` (int null), `pko_quote_only` (bool), `pko_supplier_id` (FK null).
- `pko_suppliers` (nouvelle table).
- `pko_shipping_surcharges` (nouvelle table).
- `pko_carrier_services` : ajout colonne `description` (libellés longs client).
- Data-migration : reseed services + grille Chronopost (3 services × 5 tranches).

### Back-office (Filament)
- `EditProductUnified` (carte "Inventaire & expédition") : ajouter classe logistique,
  toggle éligible franco, prix transport produit (classe C), select fournisseur.
- Resource `SupplierResource`, resource `ShippingSurchargeResource`.

### Logique checkout (`pko/lunar-shipping-common`)
- **Corriger** `LivePricingResolver::resolveFromGrid()` + `fallbackQuoteFromGrid()` → prix par
  service. *(bloquant)*
- Nouveau `FrancoModifier` (franco 350 € HT sur Chrono 13).
- Nouveau `ProductTransportModifier` (classe C prix par produit).
- Nouveau `SurchargeModifier` (suppléments auto).
- Ordre d'enregistrement des modifiers dans `ShippingCommonServiceProvider` (carriers →
  franco → suppléments).
- `WeightCalculator` : helper sous-total HT éligible franco / détection lignes exclues.

### Front (storefront)
- `ShippingOptions::mount()` : défaut Chrono 13.
- `shipping-options.blade.php` : 3 cartes descriptives + bandeaux franco/exclusion/multi-colis.
- Fiche produit + ligne panier : badge disponibilité (stock Weklo / commande fournisseur) +
  délai.

### Post-paiement (V2)
- `CreateCarrierShipmentJob` / `OrderShipmentObserver` : découpage multi-expédition par origine.

---

## 8. Découpage en lots parallélisables (proposition)

| Lot | Contenu | Dépendances |
|---|---|---|
| L1 | Migrations + reseed grille/services + correctif `resolveFromGrid` par service | — |
| L2 | `FrancoModifier` + helpers `WeightCalculator` + flags produit franco/classe | L1 |
| L3 | Back-office produit (EditProductUnified) + `SupplierResource` | L1 |
| L4 | Front panier (3 cartes, défaut Chrono 13, bandeaux) | L1, L2 |
| L5 | Suppléments (`pko_shipping_surcharges` + `SurchargeModifier`) | L1 |
| L6 (V2) | Multi-expédition post-paiement + routage BL-neutre | L1, L3 |
| Lmerge | Consolidation sur `main` + tests bout-en-bout | tous |

> Note merge : relire le diff cumulé complet (les modifiers se partagent
> `ShippingCommonServiceProvider` et l'ordre d'enregistrement → conflit probable L2/L5).
