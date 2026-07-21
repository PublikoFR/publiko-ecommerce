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

> **Piège — la visibilité de la grille ne doit pas dépendre de l'état Alpine.**
> `.mlib-grid` portait `x-show="pending.length > 0 || {{ $medias->count() }} > 0"`.
> Quand `pending` n'est pas résolu, l'expression évalue à *falsy* et Alpine pose
> `display:none` sur la grille **entière**, alors que le serveur a bien rendu les
> tuiles. Symptôme très trompeur : le compteur du dossier affichait « (122) », le
> HTML servi contenait les 122 `<img>` (URLs en HTTP 200) et le CSS compilé était
> correct — mais rien ne s'affichait, seule la dropzone restait visible.
> Le bug est resté invisible tant qu'aucun dossier n'avait de média (grille vide
> = masquée de toute façon) ; il n'est apparu qu'au premier import réel.
>
> **Correctif appliqué** : `x-show` n'est conservé que dans le cas « dossier
> vide » (où seules les tuiles optimistes d'upload comptent). Dès que le serveur
> sait qu'il y a des médias, la grille est rendue sans condition JS.
>
> **Correctif connexe** : la factory `window.pkoMediaLibraryUploader` a été
> déplacée de `@script` vers `@assets`. Livewire sérialise le contenu d'un bloc
> `@script` dans l'attribut `wire:effects` et ne l'exécute qu'après
> l'initialisation du composant, alors que `x-data="pkoMediaLibraryUploader()"`
> est évalué par Alpine dès l'init de l'arbre DOM — la factory pouvait donc être
> indéfinie au moment de son usage. `@assets` est injecté une seule fois, en
> amont, comme véritable `<script>`. `this.$wire` reste résolu à l'exécution dans
> les méthodes du composant, donc rien à adapter côté code.
> À noter : ce second changement seul **n'a pas suffi** à rétablir l'affichage —
> c'est la suppression du `x-show` qui a débloqué la grille. La cause exacte de
> la non-résolution de `pending` n'a pas été isolée ; **l'uploader (drag & drop,
> « Coller une URL ») reste donc à vérifier**, il dépend du même scope Alpine.

**Dossier par défaut** : setting `media.default_folder_id` (clé de `pko_storefront_settings`, valeur = ID du dossier). Sélectionné automatiquement à l'ouverture (page comme modale) et épinglé en tête de la sidebar des dossiers. Tant que le setting n'est pas posé, fallback sur le dossier dont `collection = 'products'`. En mode page, une étoile au survol de chaque dossier (`setDefaultFolder(int $id)`) permet de changer le choix.

### Service `MediaLibraryImporter` (import URL + dédup)

`packages/pko/lunar-media-core/src/Services/MediaLibraryImporter.php` — importe un
fichier distant (URL) **dans la médiathèque custom** (média Spatie possédé par un
`TomatoPHP\FilamentMediaManager\Models\Folder`, `collection = folder.collection`),
avec **déduplication à deux niveaux** stockée en `custom_properties` :

1. `source_url` — réutilise le média si la même URL a déjà été importée (zéro re-download).
2. `sha1` — réutilise si le même binaire existe déjà (même image, URL différente) — calculé après download.

API :
```php
$media = app(MediaLibraryImporter::class)->importFromUrl($url, 'products', $name);           // par slug de collection
$media = app(MediaLibraryImporter::class)->importIntoFolder($folder, $url, $name);           // dossier explicite
// PDF (notices) : passer MediaLibraryImporter::DOCUMENT_EXTENSIONS en 4e arg.
$folder = app(MediaLibraryImporter::class)->resolveFolder('documents');                       // firstOrCreate par collection
```

Extension détectée depuis l'URL sinon les magic bytes (JPEG/PNG/GIF/WEBP/SVG/**PDF**).
Dépendance ajoutée au composer du package : `tomatophp/filament-media-manager: ^1.1`
(le modèle `Folder` = propriétaire des médias de bibliothèque).

Consommé par **ai-importer** (images + documents produit) et destiné à remplacer le
download inline de `PkoMediaLibrary::importFromUrl` (dédup — TODO, non fait pour l'instant).

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
- **Données Lunar existantes** : aucune migration automatique de `media_has_models` → `pko_mediables`. Prévoir un artisan `pko:migrate-lunar-media` en phase 2 si besoin de conserver les galeries produits existantes.
- **Upgrade Lunar** : la reflection sur `LunarPanelManager::$resources` et les overrides de `getDefault*()` dépendent de l'API interne. À revérifier à chaque upgrade Lunar majeur.

### Conversions d'image manquantes sur les médias-bibliothèque (helpers storefront)

Les médias importés/uploadés dans la médiathèque appartiennent au modèle `Folder`
(`tomatophp/filament-media-manager`), **pas** aux modèles Lunar. Ils n'ont donc
**aucune conversion Lunar** (`large`/`medium`/`small` de `StandardMediaDefinitions`) :
`generated_conversions = []`. Un `$media->getUrl('large')` côté storefront lève
`InvalidConversion: There is no conversion named 'large'`.

Deux helpers (autoload `src/helpers.php` du package) contournent ça — **fix temporaire (option 2)** :

- **`pko_media_url(?Media $media, string $conversion = ''): string`** — retourne l'URL
  de la conversion si elle a été générée (`hasGeneratedConversion`), sinon l'**original**.
  Sûr pour les médias natifs (conversion servie) comme bibliothèque (fallback original).
- **`pko_product_thumbnail(?Product $product): ?Media`** — résout la vignette produit
  depuis `pko_mediables` (groupe `product`, 1re position), fallback `$product->thumbnail`
  natif. Nécessaire car l'importeur/éditeur n'attachent que du média-bibliothèque
  (thumbnail natif = null → cards sans photo).

Utilisés dans `product-page.blade.php` (`large`/`small`), `product-card.blade.php`
(`medium`), `search-autocomplete.blade.php` (`small`). **Reste à faire (option 1)** :
faire générer les conversions Lunar sur les médias-bibliothèque (swap du modèle `Folder`
déclarant `StandardMediaDefinitions` + `media:regenerate`) pour servir des images
optimisées plutôt que les originaux plein format. Le panier (`getThumbnail()`) reste sur
le média natif (dégrade en sans-image pour les produits importés) — à migrer en option 1.

