# pko/lunar-customer-auth — compte pro, prix négociés, remises client

Package `packages/pko/customer-auth` (namespace `Pko\CustomerAuth`). Ce fichier documente
la partie **prix client** ; l'inscription pro / validation manuelle / SIRENE est décrite dans
le `README.md` du package.

## Point d'application unique du prix client

`Pko\CustomerAuth\Pricing\NegotiatedPricePipeline`, enregistré dans
`config/lunar/pricing.php` (`pipelines`). Il s'exécute à **chaque** résolution de prix Lunar
(`Pricing::for($variant)->get()` : fiche produit, price-gate, panier, checkout, commande) et
remplace `matched` par le prix client s'il est plus bas.

Le calcul est délégué à `Pko\CustomerAuth\Pricing\CustomerDiscountResolver` :

```
prix final = min(prix déjà résolu par Lunar, prix négocié, prix remisé)
prix remisé = prix déjà résolu × (1 − plus forte remise %)
```

- **Prix déjà résolu** = ce que Lunar a choisi (prix de base, prix groupe client, prix
  dégressif par quantité). La remise % se calcule sur ce prix, **jamais** sur le prix négocié
  (pas de cumul négocié + remise : le plus bas gagne).
- **Arrondi** : calcul entier en points de base (`12.5 %` → `1250`), arrondi au centime le
  plus proche (`applyBasisPoints()`). Aucun float sur un montant.
- Égalité négocié = remisé → la source retenue est `negotiated` (pas de badge remise).
- Utilisateur invité ou staff (admin hors impersonation) → pipeline inactif.
- Promotions/coupons Lunar : arbitrés ensuite au niveau du panier (cf. « Non-cumul avec les
  promotions / coupons Lunar »).

## Remises client (%)

Table `pko_customer_discounts` — modèle `Pko\CustomerAuth\Models\CustomerDiscount`,
relation `Customer::customerDiscounts()` (ajoutée par `resolveRelationUsing`).

| Colonne | Rôle |
|---|---|
| `customer_id` | FK `lunar_customers`, cascade |
| `target_type` | `collection` \| `brand_family` \| `brand` (`CustomerDiscount::TARGET_*`) |
| `target_id` | id de la collection Lunar / de `pko_brand_families` / de la marque Lunar |
| `percent` | `decimal(5,2)` (ex. `12.50`) |

Unique `(customer_id, target_type, target_id)`. Pas de FK sur la cible : elle est polymorphe
et `pko_brand_families` appartient à `pko/catalog-features` (lecture de la colonne
`lunar_products.pko_brand_family_id` uniquement, sans dépendance de classe). Remises
**permanentes** (pas de dates).

Règles :

- **Collections** : une remise sur une famille couvre ses sous-familles et sous-sous-familles
  (arbre nestedset, bornes `_lft/_rgt` par `collection_group_id`). L'inverse est faux.
- **Plus forte remise gagne** parmi toutes les cibles couvrant le produit (collections du
  produit et leurs ancêtres, famille fabricant, marque). Pas de cumul entre remises.
- Un utilisateur rattaché à plusieurs clients : on prend la plus forte de toutes leurs remises
  (même logique que le prix négocié, `min`).
- **Produit non remisable** : colonne `lunar_products.pko_not_discountable` (bool, défaut
  `false`). Aucune remise % ; le prix négocié reste applicable.

## Non-cumul avec les promotions / coupons Lunar

Règle : la remise client % ne se cumule pas avec les promotions et coupons Lunar ; le client
obtient **la meilleure des deux au niveau du panier**. Exception : les **bons d'achat
fidélité** se cumulent toujours et se déduisent **après** l'avantage retenu.

Mécanisme : `config/lunar/cart.php` remplace l'étape `Lunar\Pipelines\Cart\ApplyDiscounts`
(classe `final`, non extensible) par `Pko\CustomerAuth\Pricing\ApplyDiscountsWithCustomerPricing`,
qui délègue à `CartDiscountArbiter` (lié en `scoped`). Les lignes arrivent au prix client
(remise % intégrée par `NegotiatedPricePipeline`). Si le client a des remises :

1. **A** = lignes au prix client, sous-total HT `A` (aucune promotion) ;
2. lignes recalculées (pipeline `cart_lines`) sous `CustomerDiscountResolver::withoutPercentDiscount()`
   → prix **sans remise %** mais **avec prix négocié** (un prix négocié n'est pas une remise
   et reste cumulable avec une promotion) ; promotions/coupons Lunar appliqués → total `B` ;
3. `B < A` → promotions retenues ; sinon (égalité incluse) l'état A est restauré (prix, remises,
   `discountBreakdown`, `freeItems`, `meta` des lignes) et aucune promotion n'est consommée
   (`$cart->discounts` vide → `markAsUsed` n'incrémente pas ses usages à la commande) ;
4. les types **cumulables** sont appliqués sur l'état retenu.

- Comparaison sur le **HT des lignes avant bons cumulables** (un bon plafonné au panier
  fausserait la comparaison). Le port (`ApplyShipping`, avant les remises) est calculé sur le
  prix client : un franco basé sur le sous-total ne bascule pas selon l'avantage retenu.
- Client sans remise → comportement Lunar standard, aucun recalcul. Client avec remises mais
  aucune ligne réellement remisée (non remisable, négocié plus bas…) → après le 2ᵉ passage
  `cart_lines`, comportement standard. Ce surcoût ne concerne que les clients remisés.
- Les remises sont relues par `Discounts::getDiscounts($cart)` à chaque calcul (le mémo de
  `DiscountManager::apply()` n'est pas utilisé).
- **Types cumulables** : `config('customer-auth.stackable_discount_types')` (vide par défaut).
  `LoyaltyServiceProvider::boot()` y ajoute `LoyaltyVoucher` : contrat par config, pas de
  dépendance de classe entre `customer-auth` et `loyalty`. Rejeté : tester la classe du bon
  depuis customer-auth (couplage) ou une interface marqueur (dépendance Composer inverse).
- **Affichage** : `CartDiscountArbiter::decisionFor($cart)` → `CartAdvantage` (null si aucune
  remise client en jeu) : `winner` (`customer_discount` | `promotion`), économies
  `customerDiscountCents` / `promotionCents`, `outrankedCoupon`, `stackedCents`. Partial
  `customer-auth::partials.cart-advantage` (via `displayFor()`, forme sérialisable) inclus dans le
  récap du panier, du checkout et du tiroir panier : « Promotion retenue » / « Votre remise
  client est retenue » / « Remise client incluse », montants comparés, code écarté, bon cumulé.
- **Code promo** (`CouponCode`) : un code battu par la remise client est refusé avec un message
  explicite (« ne se cumule pas avec votre remise client… ») et retiré du panier ; un code déjà
  posé qui devient moins avantageux (panier modifié) reste sur le panier, affiché « non cumulé ».

## Back-office

- **Fiche client → onglet « Remises »** : `CustomerDiscountsRelationManager`, ajouté par
  `CustomerDiscountsExtension` (ResourceExtension sur `PkoCustomerResource`, après « Prix
  négociés »). Éditable depuis la page View (`isReadOnly()` forcé à `false` : Filament rend
  sinon les relation managers en lecture seule sur une page View).
- **Ventes & Clients → « Remises clients »** : `CustomerDiscountResource` (page unique
  `ManageRecords`, édition en modale), enregistrée par `CustomerAuthPlugin`. Vue de toutes les
  remises tous clients confondus, filtres client / type + cible, recherche par client.
  Policy Shield `App\Policies\CustomerDiscountPolicy` (permissions `*_customer::discount`).
- Formulaire et colonnes partagés : `CustomerDiscountResource::targetFields()` /
  `discountColumns()`. Validation : `0 < % ≤ 100`, 2 décimales max, unicité
  client + type + cible (même règle que l'index unique SQL).
- Libellés des cibles : `Pko\CustomerAuth\Support\CustomerDiscountTargets` (scoped, une
  requête par type et par requête HTTP). Collections = chemin complet « Famille › Sous-famille ›
  Sous-sous-famille » dans l'ordre de l'arbre, groupé par groupe de collections ; familles
  fabricant = « Marque · CODE — Libellé » (lecture directe de `pko_brand_families`, sans
  dépendance de classe vers `pko/catalog-features`).
- **Fiche produit** (`EditProductUnified`, carte Organisation) : interrupteur « Non remisable »
  → `lunar_products.pko_not_discountable`.

## Exposer le détail (prix barré, badge, non-cumul)

```php
$breakdown = app(CustomerDiscountResolver::class)->priceFor($variant, $user, $currency, $qty);

$breakdown->originalCents;            // prix avant remise (prix barré)
$breakdown->finalCents;               // = Pricing::...->matched
$breakdown->source;                   // catalog | negotiated | discount
$breakdown->isDiscounted();           // la remise % fixe le prix final → badge « -X % »
$breakdown->appliedDiscountPercent(); // 12.5 si appliquée, sinon null
$breakdown->discountPercent();        // plus forte remise couvrant le produit, appliquée ou non
```

`priceFor()` passe par le `PricingManager` Lunar (donc par le pipeline) : le détail est
toujours cohérent avec le prix réellement facturé. `$user` omis → utilisateur connecté.

## Affichage front (prix barré + badge)

`Pko\Storefront\Support\CustomerPriceDisplay` (package storefront, dépendance **souple** :
sans customer-auth, prix Lunar brut) traduit le détail en objets `Price` pour les vues :

- `forVariant($variant)` → utilisé par `<x-storefront.price-gate>` (fiche produit, cartes de
  listing, collections, recherche, accueil, produits associés) ;
- `forCartLine($line)` → `CartPage` et `CartDrawer` (champs `unit_original`, `discount_badge`).
  Le barré n'apparaît que si le détail recalculé égale **exactement** `unitPrice` de la ligne
  (filet de sécurité : jamais un barré incohérent avec le prix facturé) ;
- `preloadProducts($products)` → appelé dans chaque vue de listing avant la boucle.

Règle d'affichage : barré + badge `-X %` (`x-ui.badge` forest solid, jamais lime : le lime est
réservé au CTA unique) **seulement** si `isDiscounted()` (la remise % fixe le prix). Prix
négocié seul, négocié plus bas que la remise, produit non remisable → prix net, sans barré.

Devis, commande et Pennylane ne recalculent rien : ils lisent `CartLine::unitPrice`
(pipeline Lunar → `NegotiatedPricePipeline`), puis les montants figés de la commande
(`CheckoutPage` → `CreateSplitQuoteOrder`, `OrderToInvoiceMapper`). Le formulaire
`quote-requests` n'a plus de lignes produit, donc aucun prix. Tests :
`tests/Feature/CustomerAuth/CustomerDiscountDisplayTest.php`.

## Performance (listings)

Le résolveur est lié en **`scoped`** (une instance par requête / job) et mémorise :

- les remises d'un jeu de clients (1 requête, + 2 pour étendre les collections à leur
  descendance) ; client sans remise → **aucune** requête produit ;
- les cibles d'un produit (marque, famille fabricant, non-remisable, collections) ;
- le prix négocié par variante/devise.

Sur un listing, appeler **une fois** avant le rendu :

```php
app(CustomerDiscountResolver::class)->preloadProducts($products); // ou preloadVariants($variants)
```

→ 2 requêtes pour tout le lot (`lunar_products` + `lunar_collection_product`). Sans preload,
un produit chargé avec `collections` (et la colonne `pko_not_discountable`) est lu sans
requête ; sinon 2 requêtes par produit, une seule fois par requête HTTP.
`flush()` vide les mémos (tests, ou après écriture de remises dans la même requête).

## Tests

`tests/Feature/CustomerAuth/CustomerDiscountTest.php` (héritage d'arbre, plus forte gagne,
min avec prix négocié, non remisable, client sans remise, absence de N+1),
`CustomerDiscountCartArbitrationTest.php` (promo plus forte, remise plus forte, bon fidélité
cumulé avec la remise, bon + promo, code refusé, affichage panier),
`CustomerDiscountAdminTest.php` (onglet client, vue globale, validation, doublons, case
produit) et `NegotiatedPriceTest.php`.
