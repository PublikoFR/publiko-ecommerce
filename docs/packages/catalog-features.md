# pko/lunar-catalog-features — caractéristiques structurées filtrables

### 7.bis.1 Problème

Migration depuis PrestaShop 8 : les produits portent des **caractéristiques filtrables** (marque, matière, usage, diamètre, norme, application…) utilisées pour alimenter le mega-menu et les filtres à facettes sur un catalogue de ~50 000 produits. On doit pouvoir :

- Lister les produits qui matchent **plusieurs** valeurs simultanément (AND multi-familles, OR intra-famille)
- Afficher les compteurs de facettes par collection
- Réordonner les familles et les valeurs depuis le back-office
- Rattacher certaines familles à des collections spécifiques (ex : « Diamètre » n'apparaît que dans « Visserie »)

### 7.bis.2 Pourquoi pas `Attribute` / `AttributeGroup` Lunar

Le système natif Lunar `Attribute` + `AttributeGroup` est **déclaratif uniquement** : il définit les champs d'un produit mais **stocke les valeurs dans la colonne JSON `lunar_products.attribute_data`**. Conséquence : aucun index MySQL exploitable pour `WHERE attribute_data->…`, donc filtrage multi-critères en O(n) sur 50k produits → inacceptable.

Les flags `filterable` / `searchable` de Lunar renvoient vers **Laravel Scout** (Meilisearch, Algolia, Typesense). Hors scope v1 : pas de service de recherche externe à opérer, pas de budget infra, pas de besoin de full-text FR sur les valeurs pour le moment.

### 7.bis.3 Solution — 4 tables custom relationnelles indexées

Package `packages/pko/catalog-features/` — mono-composer PSR-4, Filament Plugin, aucune modif `vendor/`.

```
pko_feature_families
  id, handle (unique), name, position, multi_value (bool, default TRUE),
  searchable (bool), timestamps
  INDEX (position)

pko_feature_values
  id, feature_family_id (FK cascade), handle, name, position,
  meta (json nullable), timestamps
  UNIQUE (feature_family_id, handle)
  INDEX (feature_family_id, position)

pko_feature_value_product                        ← pivot produit ↔ valeur
  feature_value_id (FK cascade), product_id (FK lunar_products cascade)
  PRIMARY KEY (feature_value_id, product_id)     ← JOIN par valeur
  INDEX (product_id, feature_value_id)           ← JOIN par produit (reverse)

pko_feature_family_collection                    ← optionnel : restreint une famille à des collections
  feature_family_id (FK cascade), collection_id (FK lunar_collections cascade)
  PRIMARY KEY (feature_family_id, collection_id)
  INDEX (collection_id)
```

**Règles de design** :

- **PK composée** + index reverse sur le pivot produit → JOIN O(log n) dans les deux sens
- **Pas de colonne `id` auto** sur les pivots (inutile, perd la PK clustered MySQL)
- `multi_value = TRUE` par défaut — un produit peut porter plusieurs valeurs d'une même famille (ex : applications multiples). Les familles mono-valeur (marque) flippent le flag
- `pko_feature_family_collection` **vide** ⇒ famille globale (visible partout). Avec lignes ⇒ famille restreinte à ces collections. Pas d'héritage nestedset côté stockage — résolution à la query via `FeatureManager::familiesFor()`
- `meta` JSON laisse place à couleur hex, icône, bornes numériques sans migration

### 7.bis.4 Extension sans patch `vendor/`

- **Relation Product → FeatureValue** ajoutée via `Product::resolveRelationUsing('featureValues', …)` dans le ServiceProvider — aucune modif du model Lunar core
- **Onglet « Caractéristiques »** injecté sur `ProductResource` via `LunarPanel::extensions([ProductResource::class => [ProductFeaturesExtension::class]])` et un `ResourceExtension::getRelations()` qui ajoute un `RelationManager`
- **Resource top-level `FeatureFamilyResource`** enregistrée via un Filament Plugin (`CatalogFeaturesPlugin`), groupe **Catalogue**, table `->reorderable('position')` pour drag-n-drop natif

### 7.bis.5 API publique — façade `Features`

Singleton bindé par le provider, façade Laravel pour appel depuis n'importe où (jobs FAB-DIS, listeners, commandes artisan, autres modules métier) :

```php
use Pko\CatalogFeatures\Facades\Features;

// Écriture
Features::attach($product, $value);
Features::detach($product, $value);
Features::sync($product, [$v1->id, $v2->id, $v3->id]); // remplace tout
Features::syncByHandles($product, [
    'marque'       => ['bosch'],
    'applications' => ['interieur', 'exterieur'],
]); // ne touche QUE les familles listées — préserve les autres attachements

// Lecture
Features::for($product);              // Collection groupée par famille
Features::familiesFor($product);      // familles applicables (globales + rattachées aux collections du produit)
Features::productsWith([1, 2, 3]);    // Builder Product filtré AND sur toutes les valeurs demandées
Features::countsFor($collection);     // [family_id => [value_id => count]] pour facettes
```

Chaque écriture dispatche un event Laravel :

- `FeatureValueAttached` — attach unitaire
- `FeatureValueDetached` — detach unitaire
- `ProductFeaturesSynced` — après `sync()` / `syncByHandles()`, payload `(product, attached, detached)`

**Un autre module peut s'abonner à ces events** sans toucher au package catalog-features (ex : invalider un cache Redis, relancer un indexeur, recompter des facettes, logguer).

### Familles fabricant (privées)

Famille de remise du fabricant (FAB-DIS : `FAM1`/`FAM1L`, `GAMME`/`FONCTION`…), importée
par l'AI Importer (cf. [ai-importer.md §7.quinquies.15decies](ai-importer.md)). **Cible
future des remises** (chantier séparé) : aucune règle de prix ne la lit encore.

- **Table** `pko_brand_families` : `id`, `brand_id` (FK `lunar_brands`, cascade), `code`
  (string 100), `label` (nullable), timestamps. **Unique `(brand_id, code)`** : un code
  n'a de sens que dans sa marque.
- **Collation binaire** `utf8mb4_bin` sur `code` (MySQL/MariaDB) : la collation par défaut
  `utf8mb4_unicode_ci` confondrait `ACM` et `acm` (et `é`/`e`), donc deux familles
  distinctes du fabricant violeraient l'unicité ou seraient fusionnées. Le code doit rester
  l'identifiant exact du tarif.
- **Produit** : `lunar_products.pko_brand_family_id` (nullable, FK `nullOnDelete`), colonne
  non fillable (assignation directe). Relation `Product::brandFamily()` déclarée par
  `CatalogFeaturesServiceProvider` (`resolveRelationUsing`), modèle `Pko\CatalogFeatures\Models\BrandFamily`.
- **Admin** : select « Famille fabricant » sous « Marque » dans l'éditeur produit unifié,
  filtré sur la marque choisie. Changer de marque vide la famille ; une famille d'une
  autre marque n'est jamais enregistrée.
- **Privé** : pas d'`#[ApiResource]` sur `BrandFamily`, aucune vue storefront ni
  ressource API ne lit la colonne. Ne pas l'exposer sans décision explicite.

### Compatibilités des pièces détachées

Règle métier : distinguer **la marque de la pièce** (marque Lunar du produit, `brand_id`)
des **équipements avec lesquels elle est compatible**. On ne saisit une compatibilité que
si elle est **documentée** (notice, catalogue fabricant) — une photo seule ne la confirme pas.

- **Table** `pko_product_compatibilities` : `product_id` (la pièce, FK `lunar_products`
  cascade), `equipment_brand_id` (nullable, FK `lunar_brands` `nullOnDelete`),
  `equipment_brand_label` (texte libre si la marque n'est pas au catalogue),
  `equipment_model`, `equipment_reference`, `compatible_product_id` (nullable, FK
  `lunar_products` `nullOnDelete`), `note` (500), `sort_order`, timestamps. Index
  `(product_id, sort_order)`, `equipment_reference`, `equipment_model`.
- **Pourquoi pas les caractéristiques** (`pko_feature_values`) : une compatibilité est un
  triplet structuré (marque × modèle × référence) avec lien optionnel vers un produit ;
  une valeur de facette est un libellé plat partagé. Les mélanger aurait pollué les
  facettes et perdu la référence exacte.
- **Pourquoi dans ce package** : même domaine (données catalogue structurées autour du
  produit), déjà auto-découvert — pas de nouveau package Composer pour une table.
- **Modèle** `Pko\CatalogFeatures\Models\ProductCompatibility` ; relation
  `Product::compatibilities()` (triée `sort_order`) via `resolveRelationUsing`.
  `equipmentBrandName()` = marque du catalogue sinon libellé libre. Le libellé libre
  est vidé à l'enregistrement dès qu'une marque du catalogue est choisie.
- **Admin** : carte « Compatibilités » de l'éditeur produit unifié (après « Documents ») :
  ajout, édition, suppression, réordonnancement (flèches haut/bas), recherche d'un
  produit compatible par nom/SKU. Persistance en diff (ids conservés), une ligne sans
  marque, modèle ni référence est ignorée.
- **Fiche produit** : bloc « Compatibilités » (tableau marque / modèle / référence / lien)
  après les documents, masqué si aucune ligne, mention « Compatibilités documentées par
  le fabricant ». Le lien n'est rendu que si le produit compatible est publié et a une URL.
- **Recherche** : `storefrontSearchMatch()` (scope commun SearchPage + SearchAutocomplete)
  matche aussi `equipment_reference` et `equipment_model` (sous-requête EXISTS).
- **Import** : non câblé dans l'AI Importer (lacune). Champ multi-valué (N lignes par
  produit) : il faut d'abord arrêter le format source (colonnes répétées, séparateur
  `marque|modèle|réf;…` ?) avec le client.

### Préfixe de référence des marques

Colonne `lunar_brands.pko_ref_prefix` (unique, 2-5 `A-Z0-9`), services
`Support\BrandReferencePrefix` / `Support\ProductReference`, extension admin
`BrandReferencePrefixExtension`, commandes `pko:brands:ref-prefixes` et
`pko:references:sync`. Détail : [docs/product-reference.md](../product-reference.md).

### 7.bis.6 Hors scope v1

- **Import FAB-DIS** — module dédié, consommera `Features::syncByHandles()`. Aucun refactor attendu côté catalog-features
- **Mega-menu cache Redis versionné** — phase 3 front, observer `Collection::saved` + `ProductFeaturesSynced`
- **Rendu filtres facettes Blade/Livewire** — phase 3, consomme `Features::countsFor()`
- **Full-text FR sur valeurs** — flag `searchable` stocké mais non câblé (route vers Scout si besoin un jour)

---

