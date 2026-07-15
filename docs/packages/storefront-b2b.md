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
| `packages/pko/customer-auth` | `SireneClient` (INSEE Sirene 3.11, clé API en en-tête + fallback pending), `RegisterProCustomer` action, Livewire pages Login/Register/Forgot/Reset + layout auth dédié, middlewares `pro.customer` + `redirect.if.pro` |
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
- `validateSiret(string): bool` — Luhn + 14 chiffres (statique). ⚠️ Luhn = on double un chiffre sur deux **depuis la droite** (longueur paire → index pairs depuis la gauche). Ce contrôle local tourne **toujours**, même vérification INSEE désactivée. La saisie est normalisée (espaces/séparateurs retirés) dans `RegisterPage::submit`, donc « 981 043 979 00021 » est accepté.
- `verify(string): SireneResult` — appelle `{base_url}/siret/{siret}` (nouveau portail INSEE Sirene 3.11) avec la **clé API unique** en en-tête `X-INSEE-Api-Key-Integration` (plus d'OAuth ni de token — l'ancien flux `api.insee.fr/token` client_credentials est déprécié). En-tête configurable via `INSEE_API_KEY_HEADER`.
- Retourne `Status::Active` (établissement actif), `Status::Inactive` (404 ou `etatAdministratifEtablissement ≠ A`), `Status::Pending` (API disabled, timeout, 5xx).

`RegisterProCustomer::handle($dto)` :
- Transaction : crée `Lunar\Models\Customer` (raison, TVA FR depuis clé, meta.siret/naf/adresse INSEE), attache group `installateurs`, crée `User` lié via pivot `customer_user`. Retourne `['user', 'customer', 'sirene']`.
- Si `Status::Inactive` → `DomainException` bloquante.
- Si `Status::Pending` → compte créé mais `sirene_status='pending'` → middleware refuse tant que non promu active.

`RegisterPage` (Livewire) après création :
- `Status::Active` → `Auth::login()` + redirection `/compte` (accès immédiat).
- `Status::Pending`/`Inactive` gérés en amont → **on ne connecte PAS** un compte pending à l'inscription.

**Anti-boucle de redirection (`ERR_TOO_MANY_REDIRECTS`)** — source unique de vérité :
`Pko\CustomerAuth\Support\ProAccess::isActivePro()` / `::denialReason()`. Un utilisateur
authentifié mais **non-actif** (SIRET pending, hors groupe, sans customer) qui accède à
`/compte` était renvoyé vers `/connexion` par `pro.customer`, que `redirect.if.pro`
renvoyait vers `/compte` (car authentifié) → boucle. La symétrie corrige les deux bouts :
- `RequireProCustomer` (gate `/compte`) refuse via `denialReason()` (message FR selon le motif).
- `RedirectIfProCustomer` (sur `/connexion`) ne redirige vers `/compte` **que** si `isActivePro()` — sinon laisse la page de connexion s'afficher.
- `LoginPage::authenticate` : après auth, un compte non-actif est redirigé vers l'accueil avec le message d'attente (jamais vers `/compte`).

Le piège se déclenchait surtout via la **connexion** (et non l'inscription) : `LoginPage`
authentifie l'utilisateur, donc la seule garde à l'inscription ne suffisait pas.

Migration `2026_04_17_120000_add_sirene_columns_to_lunar_customers` : `sirene_status` (indexed), `sirene_verified_at`, `naf_code`.

### Suppression d'un client = anonymisation RGPD (jamais de delete physique)

Toutes les FK vers `lunar_customers` (orders, addresses, carts, pivots user/group/discount)
sont en `NO ACTION` et le modèle Lunar `Customer` n'a **ni SoftDeletes ni cascade** → un
`DeleteBulkAction` natif plante en `1451 FK constraint` dès qu'un client a une commande /
un compte lié. Décision (RGPD + compta/Pennylane) : **on n'efface jamais la ligne client**.

`App\Filament\Extensions\CustomerAnonymizeExtension` (extension sur `CustomerResource`)
remplace le bulk delete par une action **« Anonymiser (RGPD) »** →
`Pko\CustomerAuth\Actions\AnonymizeCustomer` :
- efface les données perso du client (`first_name`/`last_name`/`company_name`/`tax_identifier`/`meta`/`sirene_*`), **garde la fiche et ses commandes** ;
- supprime adresses + paniers du client, détache groupes/remises ;
- **supprime les comptes de connexion (`User`) liés** : les FK `NO ACTION` vers `users` sont dénouées avant (les commandes sont conservées, `lunar_orders.user_id` → `null` ; `discount_user`/`customer_user`/carts supprimés).

### Suppression d'un groupe client = garde-fou (jamais de 1451)

Même schéma : toutes les FK vers `lunar_customer_groups` (customers, collections, prices,
products, shipping, tax, discounts) sont en `NO ACTION`. `App\Support\CustomerGroupGuard::blockReason()`
est la source unique de vérité et **bloque** (message FR) : le groupe **par défaut** Lunar
(`default=1`), le **groupe pro** (`config('customer-auth.default_customer_group_handle')`,
`installateurs`), et tout groupe **encore référencé** (liste des N clients/tarifs/produits…).
Un groupe custom sans aucune référence reste supprimable. `App\Filament\Extensions\CustomerGroupDeletionGuardExtension`
applique le garde-fou aux **deux** chemins : bulk delete de la liste (`extendTable`) et
delete de la page d'édition (`headerActions`, enregistrée aussi sur `EditCustomerGroup`).

Env requis pour INSEE : `INSEE_ENABLED=true` + `INSEE_API_KEY` (clé API unique du portail INSEE ; par défaut `INSEE_ENABLED=false` → fallback pending, admin valide manuellement).

**Page de configuration Back-office** (depuis 2026-07) : `Configuration → Réglages → Vérification SIRET` (`App\Filament\Pages\SireneConfig`). Permet d'**activer/désactiver** la vérification (toggle persisté dans le `Setting` `sirene.enabled`, indépendant de `.env`) et de **gérer les clés API via le système Secrets** (source `.env` **ou** base de données chiffrée, comme Stripe — module `insee`). Bouton « Tester la connexion INSEE » (requête token OAuth). Le `SireneClient` est re-liaisonné dans `AppServiceProvider::boot()` pour lire activation (Setting) + clés (Secrets) avec repli sur la config `.env`. Détails du système : `docs/packages/secrets.md`.

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
# Nouveau portail : clé API unique en en-tête (plus d'OAuth consumer key/secret)
INSEE_ENABLED=false
INSEE_BASE_URL=https://api.insee.fr/api-sirene/3.11
INSEE_API_KEY=
# INSEE_API_KEY_HEADER=X-INSEE-Api-Key-Integration  # défaut, à surcharger si besoin
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
| **Slides accueil** | `HomeSlide` | CRUD modal, reorder drag-n-drop (`position`), color pickers fond/texte, dates début/fin, CTA. Cache `pko.home.slides.v1` flush au save. |
| **Tuiles accueil** | `HomeTile` | 4 cards promotionnelles (titre, sous-titre, image, CTA, reorder). Cache `pko.home.tiles.v1`. |
| **Offres du moment** | `HomeOffer` | Badge (ex. -25%), image, date fin, CTA. Cache `pko.home.offers.v1`. |
| **Actualités** | `Post` | RichEditor Filament, slug auto depuis titre, cover, extrait, status draft/published, date publication. Cache `pko.home.posts.v1`. |
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
- **Cache Navigation versionné** : observer `Collection::saved` qui invalide `pko.storefront.nav.roots.v1`.
- **Factures PDF** : page `/compte/factures` = placeholder. Génération via Spatie Browsershot ou équivalent.
- **Loyalty UI storefront** : `/compte/fidelite` est branché sur `LoyaltyManager::getCustomerSnapshot()` mais le rendu barre/cadeaux est minimal — design à finaliser.
- **Adresses CRUD** : `/compte/adresses` liste les adresses mais le bouton Ajouter est désactivé (follow-up Livewire create/edit).
- **Multi-user société** : 1:1 User↔Customer phase 1. Upgrade = pivot roles + invitation flow.
- **Reviews** : `/home` prévoyait un bloc reviews (non inclus — intégration Avis Vérifiés en follow-up).
- **SEO** : sitemap XML, schema.org Product/Organization/Store, meta tags dynamiques (scope phase suivante).

