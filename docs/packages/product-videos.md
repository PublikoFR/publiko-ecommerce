# pko/lunar-product-videos — vidéos multi-provider

Package `packages/pko/product-videos/` (namespace `Pko\ProductVideos\`), `ProductVideosServiceProvider`. Vidéos multi-provider attachées à un `Lunar\Models\Product`, 1 table dédiée.

### Providers supportés

| Provider | Détection (regex) | Embed URL |
|---|---|---|
| YouTube | `watch?v=`, `embed/`, `shorts/`, `youtu.be` | `https://www.youtube.com/embed/{id}` |
| Vimeo | `vimeo.com/{id}`, `player.vimeo.com/video/{id}` | `https://player.vimeo.com/video/{id}` (thumbnail récupérée via oEmbed à l'ajout) |
| Dailymotion | `dailymotion.com/video/{id}`, `dai.ly/{id}` | `https://www.dailymotion.com/embed/video/{id}` |
| MP4 | URL path se terminant par `.mp4` (query autorisée) | URL brute, lue via `<video controls>` |

Tout autre format → `UnsupportedVideoUrlException`. Détection centralisée dans `VideoUrlResolver` ; thumbnails YouTube/Dailymotion déduites de l'ID seul, Vimeo récupérée via `OembedClient` (1 GET `vimeo.com/api/oembed.json`, timeout 3s, failure silencieuse).

### Table `pko_product_videos`

`id, product_id FK cascade, url text, provider string, provider_video_id nullable, thumbnail_url text nullable, title nullable, sort_order int, timestamps`
Index `(product_id, sort_order)`. Pas d'index UNIQUE (`url` est TEXT) — unicité garantie côté app via `ProductVideoManager::addIfNotExists()`.

### API publique — `ProductVideoManager`

```php
exists(Product $p, string $url): bool
addIfNotExists(Product $p, string $url, ?string $title = null): ?ProductVideo
add(Product $p, string $url, ?string $title = null): ProductVideo        // throws UnsupportedVideoUrlException
sync(Product $p, array $urls): array{added,skipped,errors}               // idempotent, pas de delete implicite
reorder(Product $p, array $idsInOrder): void
delete(ProductVideo $v): void
```

Toute intégration externe (admin UI, ai-importer, Artisan command) passe par ce service.

### Relation dynamique

`ProductVideosServiceProvider::boot()` → `Product::resolveRelationUsing('videos', fn (Product $p) => $p->hasMany(ProductVideo::class)->orderBy('sort_order'))`. Aucune modif `vendor/lunar/`.

### Composant storefront — `<x-pko-product-video>`

Deux signatures : `:video="$productVideo"` (relation ORM) ou `:url="https://..."` (ad-hoc).
Rendu wrapper responsive `aspect-ratio: 16 / 9` (pas d'override v1). `<iframe lazy>` pour YT/Vimeo/DM, `<video controls preload=metadata>` pour MP4. Branchement dans le thème manuel (le package n'injecte pas automatiquement dans la page produit storefront).

### Section admin — `EditProductUnified`

Nouvelle card "Vidéos" entre **Médias** et **Description longue**. Props Livewire :
- `public array $videos` — rows `[id,url,title,provider,thumbnail]`, rechargée depuis `$product->videos` au mount
- Actions : `addVideoRow()`, `removeVideoRow(int)`, `detectVideoProvider(int)` (appelé sur `wire:change` de l'URL), `reorderVideos(array $ids)`
- Save : `persistVideos()` → diff suppressions + `addIfNotExists` sur les nouvelles URLs + `update(title)` sur les existantes + `reorder()` final

Drag&drop via Alpine directive `x-sortable` (JS `packages/pko/product-videos/resources/js/sortable-init.js`, SortableJS chargé depuis CDN à la demande), enregistré via `FilamentAsset::register()`.

### Data migration CSV → table

`2026_04_20_110002_migrate_attribute_data_videos` : scanne `attribute_data.videos` (ex-format CSV string écrit par `LunarProductWriter`), insère chaque URL via `addIfNotExists`, puis retire la clé du champ. Irréversible (down = noop).

### Intégration `pko/ai-importer`

`LunarProductWriter::write()` appelle directement `ProductVideoManager::sync($product, $videoUrls)`. Plus de stockage sur `attribute_data.videos`. Méthode `ProductImagePipeline::stashVideoUrls()` supprimée.

### Permission Shield

`manage_product_videos` (guard `staff`) — créée par `ProductVideosPermissionsSeeder`, assignée à `super_admin`.

### Rejets documentés

- **Pas de colonne `aspect_ratio`** — tous les embeds en 16:9, décision validée pour v1 (cohérence visuelle > flexibilité éditoriale).
- **Pas de vidéos par variant** — rattachement produit-level uniquement ; si besoin plus tard, ajouter `variant_id nullable`.
- **oEmbed Vimeo uniquement** — un unique appel à `vimeo.com/api/oembed.json` lors de l'ajout d'une URL Vimeo (seul provider sans pattern CDN déterministe). Résultat persistant en DB (`thumbnail_url`), failure silencieuse si timeout/4xx/5xx. Pas d'autre fetch réseau pour les titres ou autres métadonnées.

