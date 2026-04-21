# pko/lunar-storefront-cms

CMS unifié multi-post-type (articles, pages, guides…) + page marque via page-builder + facets réactives + sanitization HTML.

## CMS unifié multi-post-type

Refactor Post/Page → `pko_posts` unique avec `post_type_id` (table `pko_post_types`). URL = `/{post_type.url_segment}/{post.slug}`.

### Tables

- `pko_post_types` : `id, label, handle, url_segment (unique), layout (nullable), icon, sort_order, timestamps`
- `pko_posts` (augmentée) : ajoute `post_type_id FK`, `content JSON` (page-builder), `seo_title`, `seo_description`. Unique composite `(post_type_id, slug)`.

### Post type reserved words

`PostType::reservedUrlSegments()` : bloque `admin, api, produit(s), collection(s), marque(s), panier/cart, recherche/search, newsletter, livraison/shipping, checkout, login/logout/register/account/mon-compte, actualites, pages, listes-d-achat, commande-rapide, magasins/stores`. Validation côté Filament.

### Routing dynamique

`routes/web.php` du package storefront-cms :
1. Routes explicites (newsletter, /marque/{slug}, redirects 301 /actualites et /pages)
2. `Route::fallback(...)` résout `/{postTypeSegment}/{slug}` en inspectant `PostType::where('url_segment', …)`. La fallback ne s'active QUE si aucune route explicite ne matche → pas de collision possible avec `/collections`, `/produits`, `/admin`, etc.

### Layout par post type

`PostController::show` lit `$postType->layout`. Si vide ou la vue n'existe pas → fallback sur `storefront-cms::posts.show`. Rend le contenu via `<x-page-builder::render :content="$post->content" :fallback="$post->body" />` (argument `fallback` du composant existant, utilisé pour afficher l'ancien `body` HTML si le contenu builder est vide).

### Data migration

Migration `2026_04_20_210001_unify_posts_and_pages` :
- Create `pko_post_types`, seed `article` + `page`
- ALTER `pko_posts` : ajouter colonnes
- UPDATE existants → post_type article
- INSERT pages dans pko_posts avec post_type page
- Drop `pko_pages`
- `change()` évité → `DB::statement('ALTER TABLE … MODIFY post_type_id BIGINT UNSIGNED NOT NULL')` pour éviter la dépendance à doctrine/dbal.


## Brand pages via builder universel

Table `pko_brand_pages` (1-to-1 avec `lunar_brands`) : `brand_id unique FK, layout, content JSON, seo_title, seo_description`. Le modèle `BrandPage` est géré via le même `<livewire:pko-page-builder>` que Post — le builder est entièrement polymorphique (prend `modelClass` + `recordId`).

Admin : `ManageBrandContent` page Filament, injectée dans `BrandResource` via `BrandContentExtension::extendPages()` + `extendSubNavigation()`. `firstOrNewForBrand()` crée le BrandPage à la première visite si inexistant. ⚠ Propriété page renommée `$data` (array) car `$layout` et `$slug` sont des propriétés statiques de `Filament\Pages\Page` — redéclaration interdite.

Storefront : route `/marque/{slug}` → `BrandController::show`. Résolution brand : Lunar Url (priorité) puis fallback sur `Str::slug($brand->name) === $slug`. Layout via `brandPage->layout` avec fallback `storefront-cms::brands.show`.


## Filtre à facettes réactif (storefront)

Deux nouvelles méthodes sur `Features` (facade `Pko\CatalogFeatures\Facades\Features`) :

- **`countsForContext(Builder $baseQuery, array $selectedByFamily, ?int $excludeFamilyId = null): array`** — Recount des valeurs d'une famille en appliquant tous les filtres des AUTRES familles. Le paramètre `excludeFamilyId` permet de garder les options sibling visibles (pattern PrestaShop : cocher Color=Red ne vide pas Blue(4)/Green(2) dans la famille Color). Retourne `[value_id => count]`.
- **`brandCountsForContext(Builder $baseQuery): array`** — Count des produits par marque après application des filtres feature. Retourne `[brand_id => count]`.

Utilisé par :
- `app/Livewire/CollectionPage.php` : pattern exclusif par famille, base query = products in collection + filtres brand actifs.
- `app/Livewire/SearchPage.php` (refactoré) : LIKE sur `JSON_UNQUOTE(JSON_EXTRACT(attribute_data, '$.name'))` + SKU/EAN/MPN, même logique de facettes.

**Gotcha** : `lunar_product_translations` n'existe PAS dans ce projet. Le nom produit est stocké directement dans `attribute_data` JSON. Utiliser `JSON_EXTRACT` pour search LIKE.


## Sécurité storefront — sanitization HTML via HTMLPurifier

`mews/purifier:^3.4` installé. Profil `pko-content` dans `config/purifier.php` (allowlist : h1-h6, p, ul/ol/li, a[href|target|rel], img[src|alt], figure, table…). URI.AllowedSchemes = http/https/mailto/tel uniquement. Target=_blank + nofollow ajoutés automatiquement sur les liens externes.

**Sinks sanitizés** :

- `Pko\PageBuilder\Services\PageBuilderManager::sanitizeHtml()` — appelée au save dans `normalizeBlock()` pour les blocs `text` (champ `html`). Toute entrée TipTap admin passe par HTMLPurifier avant persist.
- `Pko\PageBuilder\View\Components\Render::__construct()` — sanitize aussi le `$fallback` (legacy `$post->body`) au render. Couvre les données pré-sanitization.

Test empirique : `'<p>Hi <script>alert(1)</script><img src=x onerror=alert(1)></p>'` → `'<p>Hi <img src="x" alt="x"></p>'`. Script + onerror strippés.

**Threat model** : attaquant = staff éditeur de contenu (rôle FilamentShield de moindre privilège) ou super-admin compromis. Victime = tout visiteur storefront, y compris clients B2B authentifiés. Avant cette sanitization, stored XSS durable exploitable par n'importe quel éditeur de contenu pour hijacker les sessions shoppers et/ou pivoter vers l'admin.

