# pko/lunar-storefront — frontoffice Livewire (phase 1)

Port du [Lunar Livewire Starter Kit](https://github.com/lunarphp/livewire-starter-kit) comme base du frontoffice public. Le starter kit est un **template d'application Laravel**, pas un package Composer : les fichiers ont été copiés manuellement dans l'app existante.

### Fichiers portés (1:1 depuis le starter kit)
- `app/Livewire/{Home,CheckoutPage,CheckoutSuccessPage,CollectionPage,ProductPage,SearchPage}.php`
- `app/Livewire/Components/{AddToCart,Cart,CheckoutAddress,Navigation,ShippingOptions}.php`
- `app/Traits/FetchesUrls.php`
- `app/View/Components/ProductPrice.php`
- `resources/views/{layouts,livewire,components,partials}/**`
- Assets : `resources/css/app.css` (no-spinner utilities + `[x-cloak]`), `resources/js/app.js`, `tailwind.config.js` (plugin `@tailwindcss/forms` + content path `vendor/lunarphp/stripe-payments/resources/views`)
- `package.json` : ajout `@tailwindcss/forms`, `@ryangjchandler/alpine-clipboard`
- `config/livewire.php` publié, `layout => 'layouts.storefront'`

Tous les fichiers PHP portés portent `declare(strict_types=1);` (CLAUDE.md §3.2).

### Routes publiques

| Méthode | URI | Component | Nom |
|---|---|---|---|
| GET | `/` | `Home` | `home` |
| GET | `/search` | `SearchPage` | `search.view` |
| GET | `/collections/{slug}` | `CollectionPage` | `collection.view` |
| GET | `/products/{slug}` | `ProductPage` | `product.view` |
| GET | `/checkout` | `CheckoutPage` | `checkout.view` |
| GET | `/checkout/success` | `CheckoutSuccessPage` | `checkout-success.view` |
| GET | `/contact` | `ContactPage` | `contact.view` |

### Page de contact (`/contact`)

Page dédiée soignée (Design System) : composant full-page `App\Livewire\ContactPage` + vue `resources/views/livewire/contact-page.blade.php` (coordonnées à gauche, formulaire à droite, composants `x-ui.*`). Remplace l'ancien post CMS vide `nous-contacter` ; les liens footer (« Nous contacter ») et le CTA header (« Demander un devis », défaut `storefront.nav.quote_url`) pointent désormais sur `/contact`.

- **Champs** : nom, e-mail, téléphone (optionnel), sujet (select), message + case de consentement RGPD (`accepted`) + **honeypot** `website` (champ caché ; si rempli → succès simulé, aucun envoi).
- **Soumission** : `ContactPage::submit()` valide puis envoie `App\Mail\ContactMessage` (`->replyTo()` = adresse de l'expéditeur) vers l'adresse contact de la boutique. Résolution : `brand_setting('contact.email')` → `config('storefront.contact.email')` (env `CONTACT_EMAIL`) → `config('mail.from.address')`. Pas de persistance en base (décision : email seul).
- **Vue e-mail** : `resources/views/mail/contact-message.blade.php` (HTML autonome, teinté DS).
- **Tests** : `tests/Feature/Storefront/ContactPageTest.php` (rendu, envoi + destinataire/reply-to, validation, RGPD obligatoire, honeypot).

### Écarts volontaires vs. starter kit

- **Non porté : `app/Providers/AppServiceProvider.php`** — celui du projet gère déjà LunarPanel (avec Shield + ResourceExtensions) ; pas d'override de `Lunar\Models\Product` via `ModelManifest::replace()`.
- **Non porté : `app/Modifiers/ShippingModifier.php`** — l'option « Basic Delivery » factice du starter kit n'a pas lieu d'être : Table Rate Shipping + drivers Chronopost/Colissimo (voir §5) fournissent les options réelles.
- **Non porté : `app/Models/Product.php` / `CustomProduct.php`** — on passe par `Lunar\Models\Product` natif + mécanismes d'extension documentés (§3).
- **Non porté : dépendances `laravel/sanctum`, `meilisearch/meilisearch-php`, `predis/predis`, `league/flysystem-aws-s3-v3`** — pas d'API storefront en phase 1 ; Redis via `phpredis` ; Scout déjà installé ; pas de S3.
- **Non porté : seeders de démo (`ProductSeeder`, `OrderSeeder`, `CollectionSeeder`, `CustomerSeeder`…)** — l'import AI (§11) et les données réelles couvrent le besoin.
- **Non porté : configs `config/lunar/*` du starter kit** — les configs sont déjà publiées et tunées (Stripe, shipping, panel, etc.).

### Dépendances NPM ajoutées
- `@tailwindcss/forms` ^0.5.9
- `@ryangjchandler/alpine-clipboard` ^2.3.0

### Checkout — corrections starter kit → Livewire 3 (2026-06)

Le `CheckoutPage` du starter kit visait Livewire 2 et était cassé sur ce projet (Livewire 3.7). Corrections appliquées :

- **Binding sur modèle Eloquent interdit en Livewire 3** : `wire:model="shipping.first_name"` sur une propriété `CartAddress` lève `Can't set model properties directly` (`ModelSynth`). Les valeurs s'affichaient mais n'entraient jamais dans l'état → validation « field required » sur des champs remplis. **Décision** : `CheckoutPage::$shipping` / `$billing` sont désormais des **arrays** (`emptyAddress()` / `addressToArray()`), reconvertis en `CartAddress` dans `saveAddress()`. Le résumé d'adrese (partial) lit l'adresse **sauvegardée** (`$this->cart->{$type}Address`), pas l'array. Test de non-régression : `tests/Feature/CheckoutBindingTest.php`.
- **Layout** : `CheckoutPage`/`CheckoutSuccessPage` passent de `layouts.checkout` (layout démo Lunar) à `layouts.storefront` (thème projet). `@stripeScripts` poussé via `@push('head')` car le layout storefront ne l'inclut pas (cf. `@stack('head')`).
- **Pays** : `getCountriesProperty()` retourne désormais `Country::orderBy('name')->get()` (était `['GBR','USA']`, jamais présents en DB → select vide). Défaut = pays boutique.
- **Prefill** : `mount()` pré-remplit l'adresse depuis le client connecté (`$cart->customer` : nom, société, email du User, `meta.phone`, `meta.sirene_address`).

### Limitations connues / follow-up

- **Recherche** : `SearchPage` repose sur `Product::search()` (Scout). Nécessite un driver configuré (Algolia/Typesense/DB) ; sinon les résultats seront vides.
- **Navigation** : `Navigation::getCollectionsProperty()` charge toutes les collections en arbre à chaque requête — à mettre en cache si le catalogue explose.
- **UI** : starter kit basique non-production ready, sera amené à être refondu (Inertia+Vue kit à surveiller).

### Menu latéral off-canvas (`x-layout.lateral-menu`)

Composant : `packages/pko/storefront/resources/views/components/layout/lateral-menu.blade.php`  
Inclus dans : **`resources/views/layouts/storefront.blade.php`** (layout projet, avant `x-layout.header`) — c'est celui réellement utilisé par toutes les pages Livewire full-page (`config/livewire.php` → `layout => 'layouts.storefront'`). Le composant `x-layout.storefront` du package l'inclut aussi mais n'est branché sur aucune route.

**Comportement** : overlay sombre + panneau off-canvas slide-in depuis la gauche. Remplace le mega-menu dropdown "Tous nos produits" de la secondary nav.

**Déclencheurs** :
- Bouton burger "Tous nos produits" dans la secondary nav (`$dispatch('open-lateral-menu')`) — déclencheur unique ; ne pas redupliquer une entrée "Tous nos produits" dans `config('storefront.nav.secondary')` sinon doublon (lien mort `<a href="#">`).
- Burger mobile dans le header (`$dispatch('open-modal-mobile-nav')`)
- Fermeture : croix, clic overlay, touche Esc

**Gotcha Blade ⚠️** : une directive Alpine `:class="{...}"` posée sur un **composant Blade** (`<x-ui.icon …>`) est interprétée par Blade comme une expression PHP (préfixe `:`) → erreur de compilation `unexpected token "{"`. Sur un composant, utiliser `x-bind:class="{...}"` (transmis littéralement au `<svg>` via `$attributes->merge`). Sur un élément HTML natif (`<div>`, `<button>`), `:class` passe sans souci.

**Structure panneaux** :
- **L1** (toujours visible) — catégories racines avec vignette image (`getFirstMediaUrl('images', 'small')`), nom (lien), chevron si enfants. Sur mobile : accordéon inline au clic du chevron. Sur desktop : **survol** de la ligne révèle le panneau L2 (uniquement si la catégorie a des enfants ; survol d'une catégorie sans enfant referme L2).
- **L2** (desktop `lg+` seulement) — enfants du nœud L1 survolé. Survol d'un item avec enfants révèle L3.
- **L3** (desktop `lg+` seulement) — petits-enfants du nœud L2 survolé.
- **Colonnes collapsées** : les wrappers L2/L3 passent en `lg:w-0` (bordure retirée) tant qu'aucun parent n'est actif → pas de colonnes vides. `hidden` mobile préservé (on ne bascule que largeur/bordure via `:class`, jamais `display`).

**Données** :
- Source : `Lunar\Models\Collection` avec relations `defaultUrl`, `children.defaultUrl`, `children.children.defaultUrl`
- Cache : `pko.storefront.nav.roots.v3` (3600 s) — clé bumpée v3 pour intégrer le filtre `pko_enabled`. Constante `StorefrontServiceProvider::NAV_CACHE_KEY` (source unique, référencée par le blade). **Invalidation auto** : `StorefrontServiceProvider::registerNavCacheInvalidation()` écoute `saved`/`deleted`/`restored` sur `Lunar\Models\Collection` **et** `Lunar\Models\Url` → toute modif de catégorie (nom, hiérarchie, `pko_enabled`, slug, création, suppression) rafraîchit le menu sans rebuild ni délai. Écoute aussi `saved`/`deleted` sur le modèle média Spatie filtré `model_type == Collection` → un changement d'**image** de catégorie invalide aussi. **Import de masse** : `TreeManager::importCollectionsPayload()` fait un `Cache::forget` explicite après commit (les `save()` unitaires passent aussi par les events, mais le flush post-commit garantit un rafraîchissement propre). (Complète l'ancien `TreeManager::toggleCollectionEnabled()` qui ne couvrait que le toggle enabled.)
- Filtre activé : `->where('pko_enabled', true)` appliqué aux L1, L2 et L3. Cache désactivé sur les nœuds ayant un ancêtre désactivé par l'effet du cascade (nestedset).

**État Alpine** : `{ open, l1, l2 }` — `l1` = id Collection L1 sélectionnée, `l2` = id Collection L2 sélectionnée. Réinitialisés à la fermeture.

### Navigation en cascade — « page de listing de catégories » (`pko_browse_children`)

Certaines branches se parcourent en **descendant les pages** plutôt qu'en tombant directement sur des produits. Cas d'usage : pièces détachées → marque → machine → pièces. Colonne `pko_browse_children` (booléenne, indexée) sur `lunar_collections`, migration `2026_07_21_120000_add_pko_browse_children_to_lunar_collections.php`.

**Activation** : depuis le TreeManager, entrée « Page de listing de catégories » du menu d'actions. Le drapeau **cascade sur toute la branche** (activation comme désactivation, via les bornes nestedset) — sans ça il faudrait cocher chaque marque puis chaque machine. Un badge « listing catégories » marque les nœuds concernés dans l'arbre.

**Comportement storefront** (`App\Livewire\CollectionPage`) :

| Situation | Rendu |
|---|---|
| Marquée, a des enfants visibles, aucun filtre actif | Cartes des sous-catégories (`showsChildCards`), pas de tri ni de pagination |
| Marquée, sans enfant (feuille) | Listing produits classique — la cascade s'arrête d'elle-même |
| Marquée, **filtre actif** | Bascule en listing produits sur **toute la branche** |
| Non marquée | Inchangé |

Deux points structurants :

- **`baseQuery()` s'élargit aux descendants** quand la catégorie est marquée (`whereBetween` sur `_lft` entre les bornes du nœud). Les produits ne sont rattachés qu'aux feuilles : filtrer depuis un niveau intermédiaire ne renverrait rien sans cet élargissement. C'est ce qui permet « toutes les pièces FAAC en 24 V » sans descendre machine par machine.
- **En mode cartes, la requête produits n'est pas exécutée** (`products` vaut `null` dans la vue) : inutile et coûteuse sur une branche de 200 catégories. Toute évolution de la vue doit donc garder les accès `$products->…` derrière le `@if (! $showsChildCards)`.

**Menu latéral** : une catégorie marquée est rendue comme un **lien simple**, sans chevron ni sous-menu au survol (`$colHasChildren` neutralisé dans `lateral-menu.blade.php`) — la navigation se fait par les pages. Le cache nav (`NAV_CACHE_KEY`) est vidé par la bascule.

**Fil d'Ariane** : `CollectionPage::getBreadcrumbItemsProperty()` remonte la chaîne d'ancêtres nestedset (`Accueil / Pièces détachées / Pièces détachées FAAC`). Auparavant seule la catégorie courante était affichée, ce qui perdait le visiteur au 3ᵉ niveau.

Couverture : `tests/Feature/Storefront/CollectionBrowseChildrenTest.php` (cartes, feuille, non marquée, remontée des produits descendants, fil d'Ariane).

### Filtrage storefront — catégories et produits désactivés

**Scopes Eloquent** (macros enregistrées dans `AppServiceProvider::boot()`) :

| Macro | Modèle cible | Comportement |
|---|---|---|
| `navVisible()` | `Lunar\Models\Collection` | `pko_enabled=true` ET aucun ancêtre nestedset désactivé (sous-requête EXISTS sur `_lft/_rgt`). |
| `storefrontVisible()` | `Lunar\Models\Product` | EXISTS au moins une collection navVisible via `lunar_collection_product`. Sous-requête indexée (pas de N+1). |

**Appliqué dans** :
- `Navigation::getCollectionsProperty()` — nav header
- `CollectionsIndexPage::render()` — page index catégories + new arrivals
- `CollectionPage::mount()` — abort 404 si la collection cible est désactivée (ou a un ancêtre désactivé)
- `CollectionPage::baseQuery()` — produits dans la collection filtrés `storefrontVisible`
- `ProductPage::mount()` — abort 404 **uniquement** si le produit possède des collections mais qu'aucune n'est navVisible. Un produit **sans aucune collection** reste affichable (traité comme « Non classé ») : sa fiche s'ouvre normalement (le fil d'Ariane n'affiche que le nom du produit). Ceci évite le 404 sur les produits mis en avant / nouveautés de l'accueil (`HomeFeaturedProducts` fallback `latest()`) qui n'ont pas de catégorie. Note : ces produits restent exclus des listings filtrés `storefrontVisible` (recherche, pages catégorie) puisque ce scope exige au moins une collection navVisible.
- `SearchPage::baseQuery()` — résultats de recherche filtrés `storefrontVisible`
- `SearchAutocomplete::render()` — suggestions collections (`navVisible`) + produits (`storefrontVisible`)
- `lateral-menu.blade.php` — L1/L2/L3 filtrés `->where('pko_enabled', true)` (redondant avec cascade, mais explicite)

**Accessibilité** : `role="dialog" aria-modal` sur le conteneur, `role="menu/menuitem"` sur les listes, `aria-expanded` sur les chevrons, focus géré via fermeture Esc, `overflow-hidden` sur `body` quand ouvert.

### Impact back-office
- Aucun. `/admin` (Filament + Shield) inchangé, routes et middlewares séparés.

