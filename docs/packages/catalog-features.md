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

### 7.bis.6 Hors scope v1

- **Import FAB-DIS** — module dédié, consommera `Features::syncByHandles()`. Aucun refactor attendu côté catalog-features
- **Mega-menu cache Redis versionné** — phase 3 front, observer `Collection::saved` + `ProductFeaturesSynced`
- **Rendu filtres facettes Blade/Livewire** — phase 3, consomme `Features::countsFor()`
- **Full-text FR sur valeurs** — flag `searchable` stocké mais non câblé (route vers Scout si besoin un jour)

---

