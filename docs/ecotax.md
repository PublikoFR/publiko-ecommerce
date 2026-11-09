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

## Affichage

Mention « dont éco-participation X € HT » : informative, jamais additionnée. Rien
n'est rendu quand l'éco-taxe vaut 0 ou `NULL`. Convention **HT** partout : tous les
prix storefront voisins sont HT (`price-gate` affiche « HT »), l'éco-taxe aussi.

- **Composant** `x-storefront.ecotax-note` (`packages/pko/storefront/.../components/storefront/ecotax-note.blade.php`) :
  props `cents` + `currency`, ou `formatted` (chaîne déjà formatée, vide = rien),
  `suffix` (défaut `HT`). Texte `text-xs text-neutral-500`, icône Lucide `info`
  avec info-bulle DEEE. À réutiliser pour toute nouvelle vue.
- **Format** : `Ecotax::format(int $cents, ?Currency $currency = null)` = formatter
  Lunar (`Price::formatted()`), même rendu que les autres prix. Exceptions : page de
  paiement de devis et e-mail de devis, qui formatent déjà tous leurs montants avec
  `number_format(…, 2, ',', ' ') . ' € HT'` — on garde ce format pour rester homogène.

| Point d'affichage | Source | Ligne | Total |
|---|---|---|---|
| Fiche produit + cartes produit (`price-gate`, `product-price`) | `forPurchasable` (variante affichée — suit le changement de variante) | unitaire | — |
| Panier (`cart-page`, `components/cart`, `cart-drawer`) | `forCartLine` / `forCart` (variante courante) | oui | oui |
| Checkout | `forCartLine` / `forCart` | oui | oui |
| Confirmation de commande (`checkout-success-page`) | `forOrderLine` / `forOrder` (**figé**) | oui | oui |
| Espace client, détail commande | `forOrderLine` / `forOrder` (**figé**) | oui | oui |
| Page de paiement de devis + `QuotePaymentLinkMail` | `forOrderLine` / `forOrder` (**figé**) | oui | oui |
| Admin, fiche commande Lunar (`OrderLinesTableExtension`, description de la colonne `unit`) | `forOrderLine` (**figé**) | oui | — |

Réactivité : les composants Livewire panier calculent la valeur par ligne (`lines.*.ecotax`)
et le total (`ecotaxTotal`, propriété publique pour le drawer, computed pour `CartPage`
et `components/cart`) à chaque rafraîchissement du panier.

Non couvert :

- **E-mails de commande** (`pko/order-notifications` via `pko/mail-templates`) : ils ne
  listent pas les lignes, seulement `:order_total` dans des contenus **éditables en
  base**. Ajouter la mention demanderait un nouveau placeholder (`order_ecotax`) dans
  `MailTemplateRegistry` / `OrderMailData` / `MailPreview` + une mise à niveau des
  contenus existants (`DefaultContentUpgradeTest`). Laissé pour une tâche dédiée.
- **Admin, total commande** : seule la mention par ligne est affichée.
