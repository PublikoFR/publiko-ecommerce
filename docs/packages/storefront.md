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
Inclus dans : `storefront.blade.php` (avant `x-layout.header`)

**Comportement** : overlay sombre + panneau off-canvas slide-in depuis la gauche. Remplace le mega-menu dropdown "Tous nos produits" de la secondary nav.

**Déclencheurs** :
- Bouton "Tous nos produits" dans la secondary nav (`$dispatch('open-lateral-menu')`)
- Burger mobile dans le header (`$dispatch('open-modal-mobile-nav')`)
- Fermeture : croix, clic overlay, touche Esc

**Structure panneaux** :
- **L1** (toujours visible) — catégories racines avec vignette image (`getFirstMediaUrl('images', 'small')`), nom (lien), chevron si enfants. Sur mobile : accordéon inline au clic du chevron. Sur desktop : clic chevron révèle le panneau L2 à droite.
- **L2** (desktop `lg+` seulement) — enfants du nœud L1 sélectionné. Clic item avec enfants révèle L3.
- **L3** (desktop `lg+` seulement) — petits-enfants du nœud L2 sélectionné.

**Données** :
- Source : `Lunar\Models\Collection` avec relations `defaultUrl`, `children.defaultUrl`, `children.children.defaultUrl`
- Cache : `pko.storefront.nav.roots.v1` (3600 s) — même clé que l'ancien dropdown header
- `TODO pko_enabled` marqué en commentaire : ajouter `->where('pko_enabled', true)` aux 3 niveaux quand la feature catégories désactivées sera activée

**État Alpine** : `{ open, l1, l2 }` — `l1` = id Collection L1 sélectionnée, `l2` = id Collection L2 sélectionnée. Réinitialisés à la fermeture.

**Accessibilité** : `role="dialog" aria-modal` sur le conteneur, `role="menu/menuitem"` sur les listes, `aria-expanded` sur les chevrons, focus géré via fermeture Esc, `overflow-hidden` sur `body` quand ouvert.

### Impact back-office
- Aucun. `/admin` (Filament + Shield) inchangé, routes et middlewares séparés.

