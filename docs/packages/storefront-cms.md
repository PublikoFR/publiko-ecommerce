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

### Partage de `pko_posts.content` avec page-builder — règle d'idempotence

La colonne `content JSON` de `pko_posts` est déclarée par **deux migrations** :
- `page-builder` → `2026_04_20_120001_add_content_to_cms_tables` (timestamp antérieur)
- `storefront-cms` → `2026_04_20_210001_unify_posts_and_pages` (timestamp postérieur)

Sur un `migrate:fresh`, la migration page-builder s'exécute **avant** celle de storefront-cms. La migration storefront-cms wrappe donc son ajout de colonne dans un guard `if (! Schema::hasColumn('pko_posts', 'content'))` pour éviter l'erreur « Duplicate column ».

**Règle** : toute migration qui ajoute une colonne à `pko_posts` doit vérifier `Schema::hasColumn()` avant d'appeler `$table->addColumn()` / `->json()` / etc. La table est partagée entre storefront-cms et page-builder — une absence de guard provoque un échec silencieux sur `migrate:fresh`.


## Brand pages via builder universel

Table `pko_brand_pages` (1-to-1 avec `lunar_brands`) : `brand_id unique FK, layout, content JSON, seo_title, seo_description`. Le modèle `BrandPage` est géré via le même `<livewire:pko-page-builder>` que Post — le builder est entièrement polymorphique (prend `modelClass` + `recordId`).

Admin : `ManageBrandContent` page Filament, injectée dans `BrandResource` via `BrandContentExtension::extendPages()` + `extendSubNavigation()`. `firstOrNewForBrand()` crée le BrandPage à la première visite si inexistant. ⚠ Propriété page renommée `$data` (array) car `$layout` et `$slug` sont des propriétés statiques de `Filament\Pages\Page` — redéclaration interdite.

**Sub-navigation : pattern obligatoire pour les `Page` hors `InteractsWithRecord`**  
`BrandResource` positionne la sous-navigation à `SubNavigationPosition::End` (colonne de droite). `ManageBrandContent` étend `Filament\Resources\Pages\Page` (pas `EditRecord`) — sans override, `getSubNavigation()` retourne `[]` et la sidebar disparaît. Fix : override `getSubNavigation()` → `static::getResource()::getRecordSubNavigation($this)` + `getSubNavigationParameters()` → `['record' => $this->record]`. Appliquer ce pattern à toute `Page` custom dans BrandResource ou tout autre resource avec sous-navigation.

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


## Mode maintenance storefront

Interrupteur d'urgence pour couper l'accès public au storefront sans arrêter le serveur.

### Mécanisme

- **Setting** : `storefront.maintenance` (bool, table `pko_storefront_settings`). Clé cachée (TTL 1h, invalidée à chaque `Setting::set()`).
- **Middleware** : `Pko\StorefrontCms\Http\Middleware\CheckStorefrontMaintenance` — **appliqué globalement au groupe `web`** via `$middleware->web(append: [...])` dans `bootstrap/app.php`. Il couvre donc **toutes** les pages front, y compris celles déclarées par les packages (compte, auth, store-locator, brands, posts…), et pas seulement `routes/web.php` — c'était le bug : les routes des packages échappaient au middleware et affichaient le site normalement. Les membres staff (`lunar_staff`) passent toujours. Les visiteurs non-staff voient `storefront-cms::maintenance` (503).
- **Exception `livewire/*`** : l'endpoint Livewire partagé (`/livewire/update`, groupe `web`) est laissé passer, car il porte aussi la soumission du formulaire de connexion admin Filament alors que le staff n'est pas encore authentifié — le bloquer enfermerait l'admin dehors. Sans risque de fuite : les pages front étant coupées au GET, aucun composant front non-staff n'est monté.
- **Panel `/admin`** : pile de middleware Filament propre (hors groupe `web`) → jamais impacté, l'admin reste toujours accessible.
- **Vue** : `packages/pko/storefront-cms/resources/views/maintenance.blade.php` — page standalone (pas de layout storefront, pas de Livewire) avec nom de marque dynamique via `brand_name()`.
- **Bandeau staff** : composant Blade anonyme `x-storefront.maintenance-banner` (`packages/pko/storefront/resources/views/components/storefront/maintenance-banner.blade.php`), inclus en tête du header partagé (`x-layout.header`) + du layout checkout. Il n'apparaît **que** pour un membre staff naviguant sur le front pendant que la maintenance est active : bandeau rouge (`bg-danger-600`) rappelant que le site est masqué aux visiteurs, avec lien vers l'admin.

### Toggle topbar admin

Composant Livewire `Pko\AdminNav\Livewire\MaintenanceToggle` (alias `admin-nav::maintenance-toggle`), enregistré dans `AdminNavServiceProvider::boot()`. Injecté dans la topbar Filament via `PanelsRenderHook::GLOBAL_SEARCH_AFTER` dans `AppServiceProvider` (vue `filament.hooks.maintenance-toggle`).

- **OFF** : bouton discret gris, icône alerte.
- **ON** : bouton orange warning, label "Maintenance active". Un clic toggle et persiste immédiatement via `Setting::set('storefront.maintenance', $active)`.

### Comportement attendu

| Visiteur | Maintenance OFF | Maintenance ON |
|---|---|---|
| Non authentifié | Accès normal | 503 maintenance (toutes routes front, packages inclus) |
| Client pro authentifié | Accès normal | 503 maintenance |
| Staff admin | Accès normal | Accès normal + bandeau rouge « site en maintenance » |
| Panel `/admin` | Non affecté (pile Filament hors groupe web) | Non affecté (login inclus) |


### Layout e-mail partagé

Composant Blade anonyme `x-storefront-cms::mail.layout` (fichier `resources/views/components/mail/layout.blade.php`) : template HTML transactionnel réutilisable par **tous** les e-mails du site (customer-auth, shipping-common, contact…). Fournit en-tête avec logo dynamique (`brand_logo()`, résolu en URL absolue via `config('app.url')`), corps via `{{ $slot }}`, et pied avec contact (`contact.email`/`contact.phone` Setting → fallback config `storefront.contact`), réseaux sociaux et liens légaux (mentions légales, CGV, données personnelles, contact).

- **Props** : `title` (balise `<title>`), `preheader` (texte d'aperçu masqué).
- **Usage** : `<x-storefront-cms::mail.layout title="…" preheader="…"> …corps… </x-storefront-cms::mail.layout>`.
- **Règle** : ne jamais recréer un `<!DOCTYPE html>` complet dans un e-mail — étendre ce layout. Branding lu depuis `Setting`/helpers, jamais codé en dur.

## Page d'accueil — FEAT-009 (2026-11-09)

Ordre : carrousel (`home-hero`) → **schéma des solutions** (`home-solutions`) → raccourcis
services (remplacement moteur, identification de pièce, devis projet — `home.services`) →
« La sélection pour les pros » → Actualités (`home-posts`) → bandeau « Votre espace professionnel ». Les tuiles
« Nos univers », « Nouveautés & vedettes » et « Offres du moment » ne sont plus rendues (le schéma
et les 3 raccourcis les remplacent) ; leurs modèles/tables restent en base, les onglets admin
correspondants sont retirés du hub **Page d'accueil**, remplacés par l'onglet **Sections**
(`Pko\AdminNav\Livewire\HomeSectionsForm`, clés `home.solutions`, `home.services`, `home.selection`,
`home.community_url`).

### Schéma « À chaque installation, sa solution »

- Composant `storefront-cms.home-solutions` (`HomeSolutions`) : SVG isométrique + liste des
  familles, synchronisés en Alpine (survol/focus d'une ligne, clic sur une pastille), carte
  « Voir la famille » en bas. Mobile : liste seule.
- **Zones fixes** = équipements dessinés (`Pko\StorefrontCms\Support\HomeSolutionZones`) :
  portail, porte industrielle, volets, moteur de portail, visiophone, clavier d'accès, caméra,
  module domotique, palette de consommables, caisse de pièces détachées.
- **Rapprochement automatique** : sans réglage admin, chaque zone pointe vers la catégorie
  **racine active** dont le nom contient un mot-clé de `HomeSolutionZones::AUTO_MATCH`
  (nom normalisé sans accents ; si plusieurs correspondent, la plus fournie en sous-catégories
  gagne). Décision du 2026-11-09 : un mapping par identifiant ne survit pas d'un environnement à
  l'autre (ids de collections différents entre local, dev et prod) — le premier déploiement dev
  affichait une home **sans le module**, faute de mapping refait sur le serveur.
- L'admin peut **imposer** une autre collection, **masquer** une zone (`hidden`), changer
  libellé/description. Une zone sans correspondance, ou liée à une collection désactivée / sans
  URL, n'est pas affichée.
- **Ordre = ordre du catalogue** (arbre nested set, comme le menu « Tous nos produits »), pas
  l'ordre des zones du dessin ; la numérotation suit cet ordre (pastille et ligne portent toujours
  le même numéro). Réordonner les catégories en admin réordonne donc aussi la liste.
- **Pastilles = picto de la famille** (1ʳᵉ image de la catégorie, le même que le menu latéral,
  via `pko_media_url`), dans une tuile blanche ; la pastille sélectionnée garde son fond blanc et
  prend un **contour vert clair** (pas de fond vert). La liste n'affiche que les intitulés : ni
  numéro ni picto (doublon avec le schéma). Une catégorie sans image retombe sur son numéro dans
  la pastille. `lift` (par zone) allonge la tige de la pastille là où des
  équipements voisins feraient se chevaucher les tuiles.
- La famille sélectionnée s'affiche dans une **tuile cliquable en bas à gauche du schéma**
  (picto sur fond blanc, sans fond ni contour, nom, description, flèche) qui mène à la catégorie ; plus de bandeau ni de bouton sous
  le schéma.
- **Pastilles dans le SVG**, ancrées en coordonnées du viewBox — et non en % par-dessus une image
  comme dans la maquette, où elles se décalaient avec la largeur.
- Le dessin est **généré** par `scripts/home-solutions-scene.py` (scène 3D projetée en isométrie
  stricte 30°, couleurs = classes Tailwind DS). Décision : la maquette mélangeait deux
  perspectives (toit sans faîtage commun, bâtiment sans volume, portail sans piliers, pastilles
  superposées) ; un générateur garantit la cohérence géométrique et rend les retouches
  reproductibles. Toute retouche passe par le script : régénérer le partial
  `livewire/partials/home-solutions-scene.blade.php` **et** reporter les ancres dans
  `HomeSolutionZones::all()`.

### Bandeau « Votre espace professionnel »

Affiché à tous ; le CTA dépend de l'état du compte via `ProAccess` (source unique) : invité →
« Ouvrir un compte pro » (seul CTA lime de la page) + « Se connecter » ; pro actif → « Accéder à
mon espace pro » ; compte en attente → motif `ProAccess::denialReason()`. Ligne « communauté »
affichée seulement si `home.community_url` est renseignée.

Tests : `tests/Feature/Storefront/HomeRedesignTest.php`.
