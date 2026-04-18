# Page d'édition produit unifiée

## Pourquoi

Le back-office Lunar natif éclate l'édition d'un produit en ~10 sous-pages (Availability, Media, Pricing, Inventory, Shipping, Variants, Identifiers, Urls, Collections, Associations). Les équipes perdent du temps à naviguer entre onglets. On veut un formulaire unique 2 colonnes (main + sidebar contextuelle), un seul bouton d'enregistrement, inspiré de la maquette `maquettes/product-page/`.

## Implémentation

### Architecture

On **n'introduit pas de Filament Resource custom** (cf. règle CLAUDE.md §3.1.9). À la place, on **subclasse** `Lunar\Admin\Filament\Resources\ProductResource` et on remplace sa page `edit` via le pattern `swapLunarResources()` déjà en place dans `AppServiceProvider`.

- `App\Filament\Resources\PkoProductResource` — masque la sous-navigation (`getDefaultSubNavigation() => []`) et remap `edit` sur la nouvelle page.
- `App\Filament\Resources\PkoProductResource\Pages\EditProductUnified` — page Filament custom (extends `Page`) avec `InteractsWithForms` pour embarquer le `MediaPicker`.
- Vue Blade : `resources/views/filament/resources/pko-product/edit-unified.blade.php` + partials (`_card`, `_switch-row`, `_chip`, `_google-preview`, `_variant-row`, `_tier-price-row`).

Les autres sous-pages Lunar (availability, pricing, etc.) restent accessibles par URL directe — utile pour le débogage et pour l'instant un filet de sécurité si un champ très particulier n'est pas couvert par la page unifiée.

### État Livewire

Toutes les valeurs scalaires (titre, sku, prix, stock, statut, SEO, etc.) sont des **props Livewire** plates sur la page. Les médias passent par un **mini Filament Form** dédié (state path `mediaData`), pour réutiliser `Pko\StorefrontCms\Filament\Forms\Components\MediaPicker` sans ré-implémenter la logique de pivot `pko_mediables` ni le drag-drop.

Un flag `$isDirty` est activé par le hook générique `updated()` et remis à `false` après `save()` pour alimenter l'indicateur en pied de page.

### Persistance (`save()`)

Tout se déroule dans une transaction DB :

1. **Product.attribute_data** — hydraté/écrit via des `Lunar\FieldTypes\Text` ou `TranslatedText` selon la valeur existante (clés : `name`, `short_description`, `description`, `meta_title`, `meta_description`).
2. **Product** lui-même : `brand_id`, `status`.
3. **Collections** — `sync($collectionIds)`.
4. **Tags** — `syncTags(collect($tagInputs))` (job Lunar `SyncTags`).
5. **Default variant** (première variante) : sku, ean, mpn, stock, backorder, purchasable, tax_class_id, weight/length/width/height.
6. **Prix** — `Price::updateOrCreate` pour le prix de base (`customer_group_id=null`, `min_quantity=1`) + upsert par ligne pour les paliers B2B (`min_quantity` > 1 ou `customer_group_id` non nul). Les paliers retirés côté UI sont supprimés.
7. **Features** (CatalogFeatures) — `FeatureManager::sync($product, $valueIds)`.
8. **Associations** (produits liés) — on `delete()` puis recrée avec `type = 'cross-sell'`.

Le slug est régénéré automatiquement par `App\Generators\PkoProductUrlGenerator` via les hooks déjà branchés dans `AppServiceProvider::boot()`.

### Médias

Le `MediaPicker` (Pko StorefrontCms) est inséré dans un mini Filament Form (`$this->form`). Après `save()` on appelle `$this->form->getState()` puis `$this->form->saveRelationships()` pour persister les entrées `pko_mediables`.

### Paliers B2B

Stockés nativement dans `lunar_prices` via le champ `min_quantity`. Pas de table custom. Chaque palier = une ligne `Lunar\Models\Price` avec :
- `priceable_type = Lunar\Models\ProductVariant`, `priceable_id = <variant>`
- `customer_group_id` nullable (null = tous les clients)
- `min_quantity` ≥ 1
- `price` en cents (facteur devise)

### Variantes

Matrice inline, paginée (10/page) via `WithPagination`. Deux actions ciblées : `updateVariantStock($id, $value)` et `updateVariantPurchasable($id, $active)` — écritures directes sans passer par le `save()` global pour l'édition rapide d'une variante.

### Caractéristiques techniques

Source : **`pko_feature_families` / `pko_feature_values`** (package `Pko\CatalogFeatures`). Les familles sont rendues dynamiquement ; les familles `multi_value` deviennent des `<select multiple>`, les autres des `<select>` simples. Sync via `FeatureManager::sync()`.

### Historique

5 dernières entrées `spatie/laravel-activitylog` filtrées sur `subject_type = Lunar\Models\Product` + `subject_id`. Pas de config supplémentaire nécessaire : Lunar active déjà `LogsActivity` sur ses modèles.

### Aperçu storefront

Lien `route('product.view', ['slug' => $defaultUrl->slug])` si la route est résolvable ; sinon bouton masqué.

### Mapping tokens maquette → classes projet

| Token maquette | Classe Filament |
|---|---|
| `#2563eb` accent | `text-primary-600`, `bg-primary-600` |
| `#eff6ff` soft | `bg-primary-50` |
| `#f5f6f8` fond | `bg-gray-50 dark:bg-gray-950` |
| `#ffffff` card | `bg-white dark:bg-gray-900` |
| `#e5e7eb` border | `border-gray-200 dark:border-white/10` |
| `#16a34a` succès | `text-success-600 bg-success-50` |
| `#d97706` warning | `text-warning-600` |
| `#dc2626` danger | `text-danger-600` |

## Dette connue / limitations phase 1

- **Pas de WYSIWYG** — la description longue est un `<textarea>` simple (HTML brut accepté). À upgrade vers `Filament\Forms\Components\RichEditor` ou TipTap si besoin esthétique.
- **Pas d'autosave** — save explicite uniquement (décision produit).
- **Pas d'i18n** — chaînes FR en dur dans le Blade (cohérent avec le reste du back-office).
- **Cost price non persisté** — champ `cost` affiché mais pas stocké (Lunar n'a pas de colonne dédiée sur `ProductVariant`). À brancher sur un champ `attribute_data` custom si le besoin est confirmé.
- **Prévisions publication programmée** — `status = 'scheduled'` + `publishAt` sont capturés mais pas encore associés à une tâche planifiée (à implémenter via command + scheduler).
- **Permissions fines** — une seule policy `catalog:manage-products` pour toute la page. Pas de split fin par section.

## Fichiers clés

- `app/Filament/Resources/PkoProductResource.php`
- `app/Filament/Resources/PkoProductResource/Pages/EditProductUnified.php`
- `resources/views/filament/resources/pko-product/edit-unified.blade.php`
- `resources/views/filament/resources/pko-product/partials/*.blade.php`
- `app/Providers/AppServiceProvider.php` (swap)
