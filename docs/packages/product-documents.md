# pko/lunar-product-documents — documents téléchargeables par catégorie

Package `packages/pko/product-documents/` (namespace `Pko\ProductDocuments\`), `ProductDocumentsServiceProvider`. Documents liés à un `Lunar\Models\Product` via une table dédiée, classés par catégories gérées en back-office.

### Tables

- **`pko_document_categories`** — référentiel global `(id, label, handle unique, sort_order)`. Géré via `DocumentCategoryResource` (groupe nav « Catalogue »).
- **`pko_product_documents`** — pivot `(id, product_id FK lunar_products, media_id FK media, category_id FK nullable, sort_order)`. Unique sur `(product_id, media_id)`.

### Modèles

- `DocumentCategory` — simple modèle Eloquent, relation `hasMany(ProductDocument)`.
- `ProductDocument` — belongsTo `Product`, belongsTo `Media` (Spatie), belongsTo `DocumentCategory` (nullable).

### Relation dynamique

`ProductDocumentsServiceProvider::boot()` → `Product::resolveRelationUsing('documents', fn => hasMany(ProductDocument)->orderBy('sort_order'))`. Aucune modification vendor.

### Admin (fiche produit)

Card « Documents téléchargeables » dans `EditProductUnified` (Livewire), entre les cartes Vidéos et Description longue.

- `public array $documents` — rows `[id, media_id, media_name, category_id, sort_order]`, rechargé depuis `$product->documents` au mount.
- Actions : `addDocumentRow()`, `removeDocumentRow(int)`, `documentPicked(int, mediaId, mediaName)` (appelé depuis Alpine.js quand la modale médiathèque confirme), `reorderDocuments(array $ids)`.
- Sélection du fichier : bouton « Choisir » par ligne dispatch `open-media-picker-modal` avec `statePath: 'document-row-{index}'`. Alpine listener sur la row réceptionne `media-picked` et appelle `$wire.documentPicked(...)`. Réutilise la modale `PkoMediaLibrary` existante sans modification.
- Save : `persistDocuments()` → diff suppressions + `updateOrCreate` sur les nouvelles entrées + update `category_id`/`sort_order` sur les existantes.
- Drag&drop : directive Alpine `x-sortable="reorderDocuments"` + handle `.pko-doc-handle` (même pattern que videos).

### Filament Plugin

`ProductDocumentsPlugin` enregistre `DocumentCategoryResource` (List/Create/Edit). Handle auto-généré depuis le libellé (Str::slug) à la création.

### Storefront

`ProductPage::getDocumentsProperty()` — gating `auth('customers')->check()` ; si non connecté, retourne `collect()`. Charge `ProductDocument::with(['media','category'])` groupé par `$category->label` (fallback « Documents »). Section HTML rendue sous le grid produit, invisible si vide ou non connecté.

### Rejets documentés

- **Pas de réutilisation de `pko_mediables`** — le pivot générique ne supporte pas `category_id` ; colonne dédiée dans une table propre, comme `product-videos`.
- **Pas de label override par produit** — le titre du media (Spatie `$media->name` ou `file_name`) sert de libellé affiché ; pas de surcharge produit-spécifique (décision v1).
- **Tous types MIME** — pas de filtre côté MediaPicker ; la responsabilité de n'uploader que des documents pertinents appartient à l'admin.
- **Accès réservé clients connectés** — pas de documents publics en v1 ; contrôle via `auth('customers')` dans la computed property storefront.

