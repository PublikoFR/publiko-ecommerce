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

## Colonne `featured` sur `lunar_products`

Ajoutée via migration custom (`Schema::table`) — flag booléen indexé, default `false`. Permet au storefront de pull un bloc "produits phares" en page d'accueil indépendamment de la taxonomie :

```php
Product::where('status', 'published')->where('featured', true)->limit(8)->get();
```

Pas d'équivalent `visible` — le statut Lunar (`published` / `draft`) joue ce rôle.

## Éditeur description longue — TipTap

Package `awcodes/filament-tiptap-editor` branché dans la page unifiée via un second Filament Form (`descriptionForm`, statePath `descriptionData`).

Profil custom :
- `->maxContentWidth('full')` — évite le centrage 5xl par défaut (grosse marge intérieure indésirable).
- Tools : heading, listes (puces/ordonnées/check), blockquote, hr, bold/italic/underline/strike/sup/sub, color/highlight, align-*, link, oembed, table, code, code-block, source (HTML brut natif).
- Outil `media` natif retiré (uploadait hors médiathèque) — remplacé par un bouton custom au-dessus de l'éditeur qui ouvre notre `MediaPickerModal`.

Vues publiées dans `resources/views/vendor/filament-tiptap-editor/` :
- `components/tools/heading.blade.php` — H1/H5/H6 masqués (H1 réservé au titre produit/SEO).
- `components/menus/image-bubble-menu.blade.php` — override : bulle sur image = bouton ⚙️ (popover réglages : **alt** + **max-width** en px/%/rem/em, posé via `style="max-width: X; height: auto;"` pour préserver le responsive) + 🗑 suppression.

Module CSS `resources/css/filament/admin/modules/tiptap-editor.css` :
- `min-height: 240px` + `resize: vertical` sur `.tiptap-prosemirror-wrapper` (poignée de redimensionnement native en bas-droite, max-h-[40rem] du package désactivé).
- Reset des `ring-1` par défaut sur `.tiptap-tool` (rendait la toolbar hideuse). Hover doux, état actif `bg-primary-500/15`.

Traçabilité images description ↔ produit :
- Les images insérées aboutissent dans le HTML stocké en `attribute_data.description`.
- Au save, `syncDescriptionImages()` parse le HTML, matche `<img src>` par `file_name` Spatie, et synchronise `pko_mediables` avec `mediagroup='product-description'`.
- Ajout ou retrait d'une image dans la description → `pko_mediables` mis à jour dans la foulée.

Import via URL dans `MediaPickerModal` :
- Panneau « Importer via URL » dans la toolbar de la modale (toggle Alpine).
- Serveur : download HTTP (timeout 15s), sniff binaire (JPEG/PNG/GIF/WebP/SVG) si l'extension est absente, création d'un `Media` Spatie dans le dossier courant, auto-sélection.
- Côté produit : plus qu'un seul bouton « Insérer une image (médiathèque ou URL) » — la modale couvre les deux flux.

Volet latéral détails dans la modale :
- Au clic sur une tuile, volet droit ouvre avec preview + métadonnées (fichier, type, taille, date) + édition inline **nom de fichier / titre / alt** (`saveFocusedMeta()`).
- Modale élargie à 1400px pour loger 3 colonnes (folders / grid / details).

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
