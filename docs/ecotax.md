# Éco-participation (éco-taxe) — modèle « dont »

FEAT 003. Socle technique : colonne, helper, figeage commande, import, éditeur admin.
L'affichage storefront / e-mails / factures s'appuie sur le helper décrit ici.

## Décision : modèle « dont »

Le **prix de vente en base INCLUT l'éco-participation**. On affiche
« dont éco-participation X € » ; l'éco-taxe ne s'ajoute **jamais** à un total.

- Montant **HT par unité**, en **cents entiers**.
- Soumise à la **TVA du produit** (elle fait partie du prix HT, donc de la base TVA).
- Alternative écartée : stocker un prix hors éco-taxe et ajouter l'éco-taxe en ligne
  séparée. Elle impose de toucher au calcul Lunar (pipelines panier, tax breakdown,
  remises) ; le modèle « dont » laisse le pricing Lunar intact.

## Stockage

`lunar_product_variants.pko_ecotax_cents` — `unsignedInteger` nullable
(migration custom `2026_11_09_000001_add_pko_ecotax_cents_to_product_variants.php`).

- `NULL` = jamais renseignée (seules ces variantes sont traitées par le rattrapage) ;
  `0` = renseignée, nulle.
- Colonne **non fillable** : assignation directe (`$variant->pko_ecotax_cents = …`),
  comme `pko_cost_price`.

## Helper `App\Support\Ecotax` (contrat)

| Méthode | Retour |
|---|---|
| `forPurchasable(mixed $purchasable): int` | éco-taxe unitaire d'une variante, 0 si absente ou pas une `ProductVariant` |
| `forCartLine(CartLine $line): int` | total de la ligne (unitaire × quantité), lu sur la variante **courante** |
| `unitForOrderLine(OrderLine $line): int` | lit **uniquement** `meta['ecotax_cents']`, 0 si absent |
| `forOrderLine(OrderLine $line): int` | unitaire figé × quantité |
| `forCart(Cart $cart): int` / `forOrder(Order $order): int` | somme sur les lignes produit (lignes `shipping` exclues) |

Une commande ne relit **jamais** la variante : si l'éco-taxe d'un produit change, le
montant d'une commande passée reste celui figé à la création.

## Figeage dans `OrderLine.meta`

Pipe `App\Pipelines\Orders\FreezeOrderLineEcotax` (`config/lunar/orders.php` →
`pipelines.creation`) : écrit `meta['ecotax_cents']` (unitaire) sur chaque ligne
produit dont la variante a une éco-taxe > 0, en conservant les meta existantes.

Placement :

- **après `CleanUpOrderLines`** : ce pipe Lunar compare (signature md5) les meta de
  chaque ligne de commande à celles des lignes de panier et **supprime** celles qui
  diffèrent. Figer avant lui ferait supprimer toutes les lignes avec éco-taxe. Pour la
  même raison, une recréation de commande depuis un panier recrée la ligne puis
  supprime l'ancienne : le figeage est rejoué, sans effet de bord.
- **avant `MarkQuoteOrderAwaitingQuote`**, qui reste **le dernier pipe** (règle projet,
  vérifiée par `tests/Feature/Ecotax/FreezeOrderLineEcotaxTest.php`).

## Import & rattrapage

Voir [packages/ai-importer.md §7.quinquies.15nonies](packages/ai-importer.md) :
clé `ecotax_cents` / alias `ecotax`, règle `price_tex + ecotax`, et commande
`ai-importer:backfill-ecotax` pour les produits importés avant ce support.

## Admin

Éditeur produit unifié (`EditProductUnified`), carte « Tarification » : champ
« Éco-participation (€ HT, incluse dans le prix) ». Chargé au mount, sauvé dans
`persistPrices()` par assignation directe. Vide = `NULL` (pas 0), virgule décimale
acceptée. Validation : ≥ 0 et ≤ prix HT.
