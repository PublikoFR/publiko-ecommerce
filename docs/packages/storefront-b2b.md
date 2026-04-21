# pko/lunar-storefront-b2b — frontoffice B2B pro (phase 2)

Transformation complète du storefront (starter kit Livewire basique porté en §13.bis) en **frontoffice B2B pro-only** inspiré de Foussier. Catalogue navigable en libre accès, **prix masqués pour les visiteurs** (CTA "Connectez-vous pour voir vos prix"), achat/panier/compte réservés aux comptes pros vérifiés SIRET.

### 15.1 Décisions arbitrées

| Sujet | Choix | Pourquoi |
|---|---|---|
| Visibilité | Strict pro (Foussier-like) | Aligné B2B, SEO préservé sur catalogue + fiches produits |
| Inscription | Auto + vérif SIRET API INSEE V3 | DX pro instantanée, fallback validation manuelle si API indispo |
| Mono-user | 1 User ↔ 1 Customer | Simple phase 1, multi-user refactor si besoin ultérieur |
| Recherche | Scout driver `database` (DB LIKE) | Zero dépendance tierce ; Typesense documenté en follow-up |
| Charte | Palette bleu pro B2B (Tailwind primary=blue, neutral=slate) | Placeholder, remplaçable via 1 token quand logo/hex fournis |
| CMS | Tables simples + Livewire, Filament admin en follow-up | Time-to-market, UI admin ajoutable sans migration |

### 15.2 Architecture en 7 packages

| Package | Rôle |
|---|---|
| `packages/pko/storefront` | Design system (tokens Tailwind, Blade UI `<x-ui.*>`), layout `<x-layout.storefront>`, header Foussier-like (top contact bar + main bar + mega-menu collections cache 1h + info banner) + footer 4 colonnes CMS + USPs, `SearchAutocomplete` Livewire |
| `packages/pko/customer-auth` | `SireneClient` (INSEE V3 + token cache 6h + fallback pending), `RegisterProCustomer` action, Livewire pages Login/Register/Forgot/Reset + layout auth dédié, middlewares `pro.customer` + `redirect.if.pro` |
| `packages/pko/account` | Layout sidebar `/compte`, 8 pages Livewire (dashboard, profil, société, adresses, commandes, commande-détail, fidélité, factures), `AccountContext` helper |
| `packages/pko/purchase-lists` | Tables `pko_purchase_lists` + `pko_purchase_list_items`, models, 3 Livewire (index, détail, picker modal) |
| `packages/pko/quick-order` | `QuickOrderPage` Livewire (table dynamique + coller-Excel), `SkuResolver` service |
| `packages/pko/storefront-cms` | Tables `pko_home_slides`, `pko_home_tiles`, `pko_home_offers`, `pko_posts`, `pko_pages`, `pko_newsletter_subscribers` ; 5 Livewire blocks home + 3 controllers (posts, pages, newsletter) |
| `packages/pko/store-locator` | Table `pko_stores`, routes `/magasins` + `/magasins/{slug}` avec carte Leaflet CDN + OpenStreetMap |

### 15.3 Design system — `<x-ui.*>` et `<x-layout.*>`

Tokens Tailwind (`tailwind.config.js`) :
```js
colors: { primary: blue, neutral: slate, success: emerald, warning: amber, danger: rose }
fontFamily: { sans: ['Inter', ...] }  // CDN Google Fonts
```

Composants UI : `button`, `input`, `select`, `textarea`, `checkbox`, `card`, `badge`, `alert`, `breadcrumb`, `dropdown` + `dropdown-item`, `modal`, `icon` (26 SVG inline, pas de Heroicons dep).

Composants layout : `header`, `footer`, `search-bar`, `logo` (SVG placeholder), `usps`, `storefront` (wrapper layout complet).

Composants storefront : `product-card` (Foussier-like avec marque + code + N variantes + price-gate + boutons Liste/Cart), `price-gate` (auth-aware wrapper), `add-to-cart` (gated + redirige vers product page si multi-variante).

### 15.4 Price gating

`<x-storefront.price-gate :product :variant size="md">` :
- Si `auth()->user()` + `Customer::sirene_status='active'` + `CustomerGroup::handle='installateurs'` → `Pricing::for($variant)->get()->matched->price->formatted()`.
- Sinon → `<x-ui.button href="/connexion" icon="user">Connectez-vous pour voir vos prix</x-ui.button>`.

Même logique sur `<x-storefront.add-to-cart>`. Routes gated par middleware `pro.customer` : `/panier`, `/checkout*`, `/compte*`, `/achat-rapide`, `/compte/listes-achat*`.

### 15.5 Inscription pro + vérification SIRET

`Pko\CustomerAuth\Sirene\SireneClient` :
- `validateSiret(string): bool` — Luhn + 14 digits (statique).
- `verify(string): SireneResult` — appelle `/siret/{siret}` API INSEE V3 avec Bearer OAuth2 client_credentials (token cache Redis 6h).
- Retourne `Status::Active` (établissement actif), `Status::Inactive` (404 ou `etatAdministratifEtablissement ≠ A`), `Status::Pending` (API disabled, timeout, 5xx).

`RegisterProCustomer::handle($dto)` :
- Transaction : crée `Lunar\Models\Customer` (raison, TVA FR depuis clé, meta.siret/naf/adresse INSEE), attache group `installateurs`, crée `User` lié via pivot `customer_user`. Retourne `['user', 'customer', 'sirene']`.
- Si `Status::Inactive` → `DomainException` bloquante.
- Si `Status::Pending` → compte créé mais `sirene_status='pending'` → middleware refuse tant que non promu active.

Migration `2026_04_17_120000_add_sirene_columns_to_lunar_customers` : `sirene_status` (indexed), `sirene_verified_at`, `naf_code`.

Env requis pour INSEE : `INSEE_ENABLED=true`, `INSEE_API_KEY`, `INSEE_API_SECRET` (par défaut `INSEE_ENABLED=false` → fallback pending, admin valide manuellement).

### 15.6 Routes publiques + gated

```
Public :
  /  (home Livewire refondue)
  /collections/{slug}  (faceted filters via catalog-features)
  /produits/{slug}
  /recherche  (DB LIKE, multi-champs)
  /magasins  /magasins/{slug}  (Leaflet CDN)
  /actualites  /actualites/{slug}
  /pages/{slug}
  POST /newsletter
  /connexion  /inscription  /mot-de-passe-oublie  /reinitialisation/{token}

Pro gated (middleware pro.customer = auth + CustomerGroup installateurs + sirene_status active) :
  /panier  /checkout  /checkout/success
  /achat-rapide
  /compte  /compte/profil  /compte/societe  /compte/adresses
  /compte/commandes  /compte/commandes/{order}
  /compte/listes-achat  /compte/listes-achat/{list}
  /compte/fidelite  /compte/factures
  POST /deconnexion

Admin :
  /admin  (Filament, Shield, intact)
```

### 15.7 Catalogue faceté (CollectionPage)

`CollectionPage` refondu :
- `#[Url(as: 'f')]` pour préservation des filtres, `#[Url(as: 'sort')]` pour tri, `WithPagination`.
- Sidebar filtres via `Pko\CatalogFeatures\Facades\Features::countsFor($collection)` + checkboxes.
- `Features::productsWith($selectedValueIds)` pour filter Product IDs, JOIN avec `whereHas('collections')`.
- Tri : nouveautés (default), prix asc/desc, nom A-Z.
- Pagination 24 items/page, grid 3 cols desktop.

### 15.8 Homepage refondue

`resources/views/livewire/home.blade.php` (utilisé par `App\Livewire\Home`) :
- `<livewire:storefront-cms.home-hero>` — carrousel slides Alpine auto-play 6s + dots + prev/next (cache 15min)
- `<livewire:storefront-cms.home-tiles>` — 4 tuiles portails/volets/automatismes/motorisations
- `<livewire:storefront-cms.home-featured>` — 6 produits collection `config('storefront.home.featured_collection_slug')` ou fallback latest
- `<livewire:storefront-cms.home-offers>` — 4 offres du moment
- Pitch SEO-friendly (paragraphe keyword-riche)
- `<livewire:storefront-cms.home-posts>` — 4 dernières actus publiées

Toutes les requêtes home cachées 15min (clé versionnée à ajouter via observer sur events Post/Slide/Tile/Offer `saved` dans un follow-up).

### 15.9 Nouvelles dépendances

- Aucun ajout Composer (tout construit sur l'existant Laravel 11 + Lunar + Livewire + Spatie Permission déjà présents).
- CDN Leaflet 1.9.4 (`/magasins`, `/magasins/{slug}`) — chargé `@push('head')` / `@push('scripts')` local à ces pages.
- CDN Google Fonts Inter (layout principal).

### 15.10 Variables d'environnement ajoutées

```env
# Inscription pro INSEE (désactivé par défaut → fallback validation manuelle)
INSEE_ENABLED=false
INSEE_BASE_URL=https://api.insee.fr/entreprises/sirene/V3.11
INSEE_API_KEY=
INSEE_API_SECRET=
INSEE_TIMEOUT=5

# Contact front (config/storefront.php)
CONTACT_PHONE="02 XX XX XX XX"
CONTACT_EMAIL=contact@example.fr
CONTACT_TAGLINE="Besoin d'un conseil ?"

# Réseaux sociaux (optionnel)
SOCIAL_FACEBOOK=
SOCIAL_INSTAGRAM=
SOCIAL_LINKEDIN=
SOCIAL_YOUTUBE=

# Bannière info + shipping
BANNER_ENABLED=true
BANNER_TEXT="Livraison offerte dès 125 € HT"
MIN_FREE_SHIPPING_CENTS=12500

# Home (optionnel)
HOME_FEATURED_COLLECTION=
```

### 15.11 Nouveaux tests de référence

Compte de test pro (seeded par `PkoCustomerSeeder`) :
- Email : `thierry.leroy@example.test`
- Password : `testing123`
- Customer `Leroy Fermetures`, SIRET `12345678900015`, group `installateurs`, `sirene_status=active`

Compte admin Filament (seeded par `PkoAdminUserSeeder`) :
- Email : `admin@example.fr`
- Password : `testing123`

### 15.12 Administration CMS (Filament)

Nouveau groupe de navigation **Storefront** dans l'admin Filament avec 7 resources et 1 page :

| Resource / Page | Modèle | Fonctionnalités |
|---|---|---|
| **Slides accueil** | `HomeSlide` | CRUD modal, reorder drag-n-drop (`position`), color pickers fond/texte, dates début/fin, CTA. Cache `mde.home.slides.v1` flush au save. |
| **Tuiles accueil** | `HomeTile` | 4 cards promotionnelles (titre, sous-titre, image, CTA, reorder). Cache `mde.home.tiles.v1`. |
| **Offres du moment** | `HomeOffer` | Badge (ex. -25%), image, date fin, CTA. Cache `mde.home.offers.v1`. |
| **Actualités** | `Post` | RichEditor Filament, slug auto depuis titre, cover, extrait, status draft/published, date publication. Cache `mde.home.posts.v1`. |
| **Pages CMS** | `Page` | RichEditor, slug unique, status (published/draft). Routes `/pages/{slug}` (CGV, mentions, FAQ, politique…). |
| **Abonnés newsletter** | `NewsletterSubscriber` | Liste read-only (pas de create), bulk delete, search + sort. |
| **Magasins** | `Store` | Sections Identité / Adresse / Contact / Horaires (`KeyValue` jour→plage), slug auto, coordonnées lat/lng. |
| **Paramètres** (page) | `Setting` | Contact (tél, e-mail, accroche), bannière info (toggle + texte + icône select), seuil livraison offerte cents, social links (FB/IG/LI/YT), USPs via `Repeater` icône+titre+sous-titre, slug collection vedette home. |

Plugins Filament : `Pko\StorefrontCms\Filament\StorefrontCmsPlugin` et `Pko\StoreLocator\Filament\StoreLocatorPlugin` enregistrés dans `AppServiceProvider`. Nav group inséré avant `Commandes`.

**Source de vérité config** : table `pko_storefront_settings` (key/value JSON) → modèle `Pko\StorefrontCms\Models\Setting` (helper static `get/set/forget`, cache Redis 1h). `StorefrontCmsServiceProvider::mergeDbSettingsIntoConfig()` au boot surcharge les valeurs de `config('storefront.*')` quand la table est peuplée (guard `Schema::hasTable` pour install/CI). Résultat : l'admin peut éditer tous les réglages frontoffice sans toucher au `.env`.

Policies Shield régénérées (`make artisan CMD='shield:generate --all --panel=admin --no-interaction'`) après ajout des nouveaux modèles.

### 15.13 Follow-up documentés (hors scope phase 2)

- **Scout Typesense** : upgrade du driver `database` → Typesense self-hosted pour performance + typo-tolerance sur 60k+ références.
- **Pays checkout** : `CheckoutPage::getCountriesProperty()` hardcodé `[GBR, USA]` (hérité starter kit) à remplacer par France/UE.
- **Cache Navigation versionné** : observer `Collection::saved` qui invalide `mde.storefront.nav.roots.v1`.
- **Factures PDF** : page `/compte/factures` = placeholder. Génération via Spatie Browsershot ou équivalent.
- **Loyalty UI storefront** : `/compte/fidelite` est branché sur `LoyaltyManager::getCustomerSnapshot()` mais le rendu barre/cadeaux est minimal — design à finaliser.
- **Adresses CRUD** : `/compte/adresses` liste les adresses mais le bouton Ajouter est désactivé (follow-up Livewire create/edit).
- **Multi-user société** : 1:1 User↔Customer phase 1. Upgrade = pivot roles + invitation flow.
- **Reviews** : `/home` prévoyait un bloc reviews (non inclus — intégration Avis Vérifiés en follow-up).
- **SEO** : sitemap XML, schema.org Product/Organization/Store, meta tags dynamiques (scope phase suivante).

