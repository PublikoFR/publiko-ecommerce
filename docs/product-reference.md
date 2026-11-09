# Référence produit boutique (préfixe marque + référence fabricant)

Règle métier : **référence boutique (SKU) = préfixe de la marque + référence
fabricant (MPN)**. Ex. Somfy (préfixe `SOM`) + `1240385` → `SOM1240385`.

## Données

| Champ | Rôle |
|---|---|
| `lunar_brands.pko_ref_prefix` | Préfixe de la marque. `string(5)`, nullable (marque pas encore arbitrée), **unique**. Migration `catalog-features` `2026_11_09_120000`. |
| `lunar_product_variants.mpn` | Référence fabricant **exacte** : jamais de trim, de mise en majuscules ni de suppression des zéros de tête ou des caractères spéciaux. |
| `lunar_product_variants.sku` | Référence boutique = préfixe + MPN brut. Lunar n'impose pas d'unicité ; c'est le code qui l'assure. |

## Préfixe d'une marque — `Pko\CatalogFeatures\Support\BrandReferencePrefix`

- Format : **2 à 5** caractères `A-Z0-9` (regex `^[A-Z0-9]{2,5}$`). Défaut sur 3
  lettres ; la longueur variable sert à départager deux fabricants.
- Proposition par défaut : 3 premières lettres **alphabétiques** du nom, translittérées
  (`Str::ascii`) et en majuscules (« Électro Pro » → `ELE`). Moins de 2 lettres (« 3M ») →
  aucune proposition, saisie manuelle.
- **Collision** : refusée, avec un message qui cite la marque détentrice et propose 3
  alternatives libres (4 puis 5 lettres, 2 lettres + une suivante, initiale + consonnes,
  préfixe + chiffre). L'admin choisit ; aucune attribution automatique en cas de conflit.

Admin : extension `BrandReferencePrefixExtension` (`ResourceExtension` sur la
`BrandResource` Lunar, pas de Resource custom) → section « Référence produit » de la fiche
marque + colonne dans la liste. Saisie normalisée (trim + majuscules). À la création, un
champ laissé vide reçoit la proposition par défaut si elle est libre. Un bouton propose un
préfixe libre.

Marques existantes : `make artisan CMD='pko:brands:ref-prefixes'` liste les propositions et
les collisions (préfixe déjà pris, ou même proposition pour plusieurs marques — aucune
n'est servie, l'admin arbitre). `--apply` n'écrit que les propositions sans collision sur les
marques **sans** préfixe : un préfixe existant n'est jamais réécrit.

## Composition — `Pko\CatalogFeatures\Support\ProductReference::compose(?Brand, $mpn)`

Point de composition **unique** (import, commande de recalcul). Retourne `préfixe . mpn`
(MPN brut), ou `null` si la marque n'a pas de préfixe ou si le MPN est vide / blanc.
Pas de garde « MPN déjà préfixé » : sans séparateur, un MPN qui commence par les lettres
du préfixe est légitime (`SOM` + `SOMA12` → `SOMSOMA12`).

## Import (`ai-importer`)

Voir `docs/packages/ai-importer.md` §7.quinquies.9. En résumé :

- SKU à la création : `sku` (forcé) → préfixe + `mpn` → `reference`.
- Rapprochement : SKU forcé, composé, `reference`, puis **marque + MPN exact**. Plusieurs
  variantes pour la même marque + MPN → ligne rejetée (« référence ambiguë »).
- Un SKU existant n'est **jamais** réécrit par l'import ; en mode `price`/`stock`, ni SKU ni
  MPN ne sont touchés.
- Une marque créée par l'import reçoit son préfixe par défaut s'il est libre ; sinon elle
  reste sans préfixe (warning dans les logs du job) et le SKU retombe sur `reference`.

## Recalcul des produits existants — `pko:references:sync`

```
make artisan CMD='pko:references:sync --dry-run'
make artisan CMD='pko:references:sync'          # confirmation, ou --force
```

Rapport par variante : **à modifier**, **déjà conformes**, **marque absente ou sans
préfixe**, **MPN vide**, **SKU cible déjà pris / ambigu** (cible portée par une autre
variante, ou composée par plusieurs variantes = même marque + même MPN). Seules les lignes
« à modifier » sont réécrites (via le modèle → observers et réindexation Scout). Le MPN
n'est jamais modifié. Jamais lancée automatiquement (ni migration ni scheduler).

Comparaisons en minuscules : la collation MySQL de `sku` est insensible à la casse.

## Recherche storefront

`storefrontSearchMatch` (macro partagée SearchPage + SearchAutocomplete) cherche déjà dans
`sku` et `mpn`. Tolérance ajoutée **côté requête uniquement** : espaces et tirets ignorés
des deux côtés pour `sku`/`mpn` (`REPLACE(REPLACE(LOWER(col),' ',''),'-','')`). La valeur
stockée n'est jamais altérée. Test : `tests/Feature/Storefront/SearchProductReferenceTest`.

## Variantes multiples

Chaque variante porte son propre MPN, donc son propre SKU composé. Deux variantes d'une
même marque avec le même MPN sont une ambiguïté : l'import les rejette, le recalcul les
laisse intactes et les liste.
