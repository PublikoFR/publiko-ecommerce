# pko/lunar-media-core — foundation média

### Objectif

Permettre à **n'importe quelle entité** (CMS, Lunar, futurs modules métier) d'être liée à un ou plusieurs médias de la médiathèque, façon WordPress : image unique (featured) ou galerie ordonnée. Remplace totalement l'usage natif Lunar/Spatie côté admin, **sans toucher `vendor/`**.

### Table pivot polymorphique

`database/migrations/2026_04_17_100000_create_pko_mediables_table.php` :

| Colonne | Type | Rôle |
|---|---|---|
| `media_id` | FK → `media.id` (cascade) | Référence Spatie Media |
| `mediable_type` / `mediable_id` | morph polymorphique | L'entité cible |
| `mediagroup` | string, default `'default'` | Groupe logique : `'cover'`, `'gallery'`, `'thumbnail'`, `'hero'`… |
| `position` | unsignedInt, default 0 | Ordre dans une galerie |

UNIQUE(`media_id`, `mediable_type`, `mediable_id`, `mediagroup`) — pas de doublon dans un même groupe. INDEX sur le triplet morph + mediagroup + position.

### Trait `HasMediaAttachments`

`packages/pko/storefront-cms/src/Concerns/HasMediaAttachments.php` — à `use` sur n'importe quel modèle. Méthodes clé :

- `mediaAttachments(?string $group = null): MorphToMany` — relation ordonnée par position, optionnellement filtrée par groupe.
- `firstMedia(string $group = 'default'): ?Media`
- `firstMediaUrl(string $group = 'default', string $conversion = ''): ?string`
- `syncMediaAttachments(array $ids, string $group)` — détache + réattache avec positions 0..N.
- `attachMedia(int $id, string $group, ?int $position = null)` / `detachMedia(int $id, ?string $group = null)`.

Pivot Eloquent : `Pko\StorefrontCms\Models\Mediable` (MorphPivot sur `pko_mediables`).

### Champ Filament `MediaPicker`

`packages/pko/storefront-cms/src/Filament/Forms/Components/MediaPicker.php` + vue `resources/views/forms/components/media-picker.blade.php`.

API :
```php
MediaPicker::make('cover')->mediagroup('cover');                           // single
MediaPicker::make('gallery')->multiple()->mediagroup('gallery');           // multi
MediaPicker::make('cover')->mediagroup('cover')->folder('blog');           // ouvre sur un dossier précis
```

`->folder(string $slug)` : optionnel. Pré-ouvre la modale picker sur le dossier dont `collection = $slug` (court-circuite le défaut). Ignoré côté mode page.

- Vide : bouton « + Choisir une image ».
- Rempli : miniature (single) ou grille (multi) avec boutons Retirer / Ajouter / Remplacer.
- Clic → `Livewire.dispatch('open-media-picker-modal', { statePath, multiple, preselected, mediagroup })`.
- `dehydrated(false)` : l'état n'est pas écrit sur le modèle. La persistance passe par `saveRelationshipsUsing()` qui appelle `syncMediaAttachments()` après save du record parent.

### Composant unifié `PkoMediaLibrary`

Composant Livewire unique `Pko\StorefrontCms\Livewire\PkoMediaLibrary` (alias `pko-media-library`) qui couvre les **deux** usages :

- **Mode page** (`pickerMode === null`) : rendu par la Filament Page coquille `Pko\StorefrontCms\Filament\Pages\PkoMediaLibrary` (route `/admin/mediatheque`). Browse, upload dropzone, import URL, CRUD dossiers, bulk select/move/delete, drawer slide-over d'édition avec usages + conversions.
- **Mode picker modale** (`pickerMode === 'single' | 'multiple'`) : monté globalement via `->renderHook('panels::body.end', ...)` dans `StorefrontCmsPlugin::register()` sur toutes les pages admin **sauf** `/admin/mediatheque` (évite la double-instance). Activé par l'event `open-media-picker-modal { statePath, multiple, preselected, mediagroup }`.

Vue racine `resources/views/livewire/pko-media-library.blade.php` qui branche sur 3 partials réutilisables dans `resources/views/partials/media-library/` :
`folders-sidebar.blade.php`, `grid.blade.php`, `details-drawer.blade.php` (modes `mpicker` inline vs `mlib` slide-over).

**Contrat d'events (inchangé)** :
- Entrée : `open-media-picker-modal` avec payload `{ statePath, multiple, preselected, mediagroup, folder? }` (le `folder` optionnel est un slug `collection` qui force l'ouverture sur un dossier donné).
- Sortie : `media-picked` avec payload `{ statePath, ids, medias: [{id, url, alt, fileName}] }`, consommé par le champ `MediaPicker` et la toolbar image TipTap de l'édition produit.
- Sortie : `media-picker-closed` (optionnel, consommé par l'overlay scroll-lock).

**Props publiques standardisées** : `selectedMediaIds: int[]` (cases cochées bulk OU sélection picker), `selectedMediaId: ?int` (cible du drawer d'édition), `currentFolderId: ?int`.

Factory JS unifiée : `window.pkoMediaLibraryUploader()` (anciennement `window.mdeMediaPicker` / `window.mlibUploader`).

**Dossier par défaut** : setting `media.default_folder_id` (clé de `pko_storefront_settings`, valeur = ID du dossier). Sélectionné automatiquement à l'ouverture (page comme modale) et épinglé en tête de la sidebar des dossiers. Tant que le setting n'est pas posé, fallback sur le dossier dont `collection = 'products'`. En mode page, une étoile au survol de chaque dossier (`setDefaultFolder(int $id)`) permet de changer le choix.

### Bascule Lunar — via `ResourceExtension` (pas de subclass)

**Pourquoi pas de subclass ?** Les Page classes Lunar (`EditProduct`, `ManageProductX`, etc.) codent en dur `protected static string $resource = ProductResource::class;`. Une subclass `PkoProductResource` ne serait donc pas interrogée par ces pages lors du rendu de la sub-navigation → tabs cassés ou routes manquantes.

**Solution retenue** : `app/Filament/Extensions/HideLunarMediaExtension.php` (étend `ResourceExtension`) implémente 4 hooks déclenchés via `LunarPanelManager::callHook()` :

- `extendPages(array $pages)` → `unset($pages['media'])` → la route `/media` n'est pas enregistrée
- `extendSubNavigation(array $pages)` → retire `Manage{Product,Collection,Brand}Media::class`
- `getRelations(array $managers)` → retire `MediaRelationManager::class`
- `extendTable(Table $table)` → filtre toute `SpatieMediaLibraryImageColumn` (utile pour la liste Brand)

Enregistré dans `AppServiceProvider::register()` sous trois clés (`ProductResource`, `CollectionResource`, `BrandResource`) — ces hooks se déclenchent car `ExtendsPages` / `ExtendsSubnavigation` / etc. appellent `callStaticLunarHook()` avec `static::class` = la resource Lunar d'origine.

Aucune subclass custom créée pour Product/Collection/Brand. Aucune ligne dans `swapLunarResources()`. Les URLs restent identiques à Lunar (`/admin/products`, etc.).

### Modèles CMS migrés

`Post`, `Page`, `HomeSlide`, `HomeTile`, `HomeOffer` : trait `HasMediaAttachments` ajouté. Colonnes `cover_url` / `image_url` **supprimées** par migration `2026_04_17_100100_migrate_cms_images_to_mediables.php` qui tente un best-effort match par `basename(file_name)` avant drop. Un accessor `getImageUrlAttribute()` / `getCoverUrlAttribute()` renvoyant `firstMediaUrl($group)` maintient la compatibilité des blade views storefront.

### Points d'attention

- **Spatie / Lunar Media natif** : le système reste techniquement accessible via `$product->getMedia(...)` (le trait `HasMedia` Spatie est toujours sur les modèles Lunar). Seul l'admin est caché. Le storefront doit migrer ses appels vers `$product->firstMediaUrl('gallery')` pour pointer sur `pko_mediables`.
- **Données Lunar existantes** : aucune migration automatique de `media_has_models` → `pko_mediables`. Prévoir un artisan `mde:migrate-lunar-media` en phase 2 si besoin de conserver les galeries produits existantes.
- **Upgrade Lunar** : la reflection sur `LunarPanelManager::$resources` et les overrides de `getDefault*()` dépendent de l'API interne. À revérifier à chaque upgrade Lunar majeur.

