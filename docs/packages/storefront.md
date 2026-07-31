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

### Combobox filtrable (`x-ui.searchable-select`)

Composant Blade anonyme (`components/ui/searchable-select.blade.php`) : alternative
au `<select>` natif dès qu'une liste dépasse la vingtaine d'entrées.

- API : `wire:model="prop"`, `:options="$collection"` (tableau/collection `[valeur => libellé]`),
  `label`, `hint`, `error`, `placeholder`, `search-placeholder`, `empty-text`.
- Liaison Livewire via `@entangle` sur le nom résolu de `wire:model` — le composant
  fonctionne donc **uniquement à l'intérieur d'un composant Livewire**.
- Recherche **insensible aux accents et à la casse** (`normalize('NFD')` + strip des
  diacritiques) : « plombier » matche « Plômbier ».
- Accessibilité : déclencheur et options sont des `<button>` (focusables clavier),
  `aria-haspopup`/`aria-expanded`/`role="listbox"`, navigation ↑/↓ + Entrée + Échap.
- Premier usage : choix du métier sur le formulaire d'inscription pro (cf.
  `docs/packages/storefront-b2b.md` §15.5).

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
| `storefrontVisible()` | `Lunar\Models\Product` | EXISTS au moins une collection navVisible via `lunar_collection_product`. Sous-requête indexée (pas de N+1). Sert à la **navigation par catégories**. |
| `storefrontSearchable()` | `Lunar\Models\Product` | `status='published'` ET possède une URL par défaut (`whereHas('defaultUrl')`). Indépendant des collections : sert à la **recherche** (un produit publié non catégorisé reste trouvable, sa fiche s'ouvre en « Non classé »). |
| `storefrontSearchMatch(term)` | `Lunar\Models\Product` | Filtre texte **partagé** par l'autocomplete et la page résultats. Cherche dans : `name`, `description`, `short_description` (attribute_data JSON), identifiants variant `sku`/`ean`/`mpn`/`gtin`, et `tags`. Insensible à la casse. |

**Appliqué dans** :
- `Navigation::getCollectionsProperty()` — nav header
- `CollectionsIndexPage::render()` — page index catégories + new arrivals
- `CollectionPage::mount()` — abort 404 si la collection cible est désactivée (ou a un ancêtre désactivé)
- `CollectionPage::baseQuery()` — produits dans la collection filtrés `storefrontVisible`
- `ProductPage::mount()` — abort 404 **uniquement** si le produit possède des collections mais qu'aucune n'est navVisible. Un produit **sans aucune collection** reste affichable (traité comme « Non classé ») : sa fiche s'ouvre normalement (le fil d'Ariane n'affiche que le nom du produit). Ceci évite le 404 sur les produits mis en avant / nouveautés de l'accueil (`HomeFeaturedProducts` fallback `latest()`) qui n'ont pas de catégorie. Note : ces produits restent exclus des listings filtrés `storefrontVisible` (recherche, pages catégorie) puisque ce scope exige au moins une collection navVisible.
- `SearchPage::baseQuery()` — `storefrontSearchable()` (produits publiés, même non catégorisés) + `storefrontSearchMatch($term)` pour la couverture texte
- `SearchAutocomplete::render()` — mêmes scopes (`storefrontSearchable` + `storefrontSearchMatch`, limit 10). Produits **d'abord**, puis catégories (`navVisible`), puis marques. Ordre du dropdown : Produits → Catégories → Marques.
- `lateral-menu.blade.php` — L1/L2/L3 filtrés `->where('pko_enabled', true)` (redondant avec cascade, mais explicite)

**Accessibilité** : `role="dialog" aria-modal` sur le conteneur, `role="menu/menuitem"` sur les listes, `aria-expanded` sur les chevrons, focus géré via fermeture Esc, `overflow-hidden` sur `body` quand ouvert.

### Header responsive — pas de débordement horizontal

Le logo de repli (`components/layout/logo.blade.php`, cas « aucun logo uploadé ») est un
SVG `viewBox="0 0 220 44"` : sa largeur suit sa hauteur au ratio 5:1. Rendu en `h-14` il
occupe **280 px**, ce qui — additionné au burger, aux gaps et au bloc actions (`shrink-0`) —
dépassait la largeur du viewport mobile. Conséquence visible : scroll horizontal, le fond
blanc du header s'arrêtant net à droite du contenu.

Règles à respecter dans le header (`components/layout/header.blade.php`) :

- Le logo est **borné en largeur** tant qu'on n'est pas en `lg` :
  `h-9 max-w-[34vw]` → `sm:h-11 sm:max-w-[200px]` → `lg:h-14 lg:max-w-none`.
- Gaps et paddings progressifs (`gap-2 sm:gap-4 lg:gap-7`, `px-1.5 sm:px-2.5`) : ne jamais
  poser un padding desktop sur la barre mobile.
- Tout libellé de longueur variable (nom de l'utilisateur connecté) est `truncate` + `max-w-*`.

**Garde-fou global** : `resources/css/app.css` pose `overflow-x: clip` sur `body`. `clip` et
non `hidden` — `overflow: hidden` sur un ancêtre casserait le `position: sticky` du header.
C'est un filet de sécurité, pas une excuse pour laisser un élément déborder.

### Ordre des étapes du checkout

`App\Livewire\CheckoutPage::$steps` : **1 adresse de livraison → 2 adresse de facturation →
3 mode de livraison → 4 paiement**. La facturation a été remontée juste sous la livraison
(2026-07-31) : les deux adresses se saisissent d'affilée, la case « Identique à la facturation »
n'a plus une étape intercalée entre elle et son effet.

`determineCheckoutStep()` est écrite en **cascade de `return`** sur l'état du panier (adresse de
livraison ? adresse de facturation ? option choisie ?), et non par incréments `+ 1` sur les
numéros d'étape. Réordonner le tunnel ne demande donc que deux choses : renuméroter `$steps` et
déplacer le `@include` correspondant dans `livewire/checkout-page.blade.php`. Verrouillé par
`CheckoutBindingTest::test_billing_address_is_the_step_right_after_shipping_address` et
`::test_same_as_billing_skips_the_billing_step`.

### Variables dynamiques dans les textes éditoriaux (`StorefrontText`)

Les textes de bandeau/USP saisis en back-office (Storefront → Paramètres) acceptent des
**variables `{{...}}`** résolues au rendu par `Pko\Storefront\Support\StorefrontText::render()`.

| Variable | Rendu | Source |
|---|---|---|
| `{{port_franco}}` | `500 € HT` | `ShippingSettings::thresholdCents()` |
| `{{port_franco_montant}}` | `500 €` | idem, sans suffixe |

Motivation : le seuil de franco était recopié en dur dans chaque bandeau
(« Livraison offerte dès 125 € HT »). Le modifier dans **Expédition → Paramètres** ne se
répercutait donc nulle part sur le front. La source de vérité unique reste
`Pko\ShippingCommon\Settings\ShippingSettings` (DB → config → défaut codé) ; les vues ne
formatent plus le montant elles-mêmes.

Règles :

- **Ne jamais recopier un seuil de franco en dur** dans un texte, une config ou une vue front.
- Points d'application actuels : `components/layout/header.blade.php` (barre utilitaire au-dessus
  du header **et** bandeau info sous le menu) et `components/layout/usps.blade.php`.
  Tout nouvel emplacement affichant un texte éditorial doit passer par `StorefrontText::render()`.
- Une variable inconnue est laissée telle quelle (on n'efface jamais la saisie utilisateur).
- Les `{{ }}` d'un `Setting` ne sont pas évalués par Blade (valeur échappée à l'affichage) :
  pas de risque d'injection de template.
- Ajouter une variable = une entrée dans `StorefrontText::values()` + `availableVariables()`
  (cette dernière alimente l'aide contextuelle du back-office).

Dépendance : `pko/lunar-storefront` requiert désormais `pko/lunar-shipping-common`.

### Impact back-office
- Aucun. `/admin` (Filament + Shield) inchangé, routes et middlewares séparés.
- Exception : l'aide contextuelle des champs « Texte » (bannière) et « USPs » de
  **Storefront → Paramètres** documente les variables disponibles.


### Disponibilité produit — le mode d'achat Lunar fait foi (2026-07-31)

**Symptôme** : un produit affiché « Sur commande » (stock fournisseur) refusait l'ajout au panier avec « La quantité dépasse le stock disponible. », et le message venait recouvrir le prix sur la carte produit.

**Cause** : `App\Livewire\Components\AddToCart::addToCart()` comparait `purchasable->stock < quantity` et ignorait le champ Lunar `ProductVariant::$purchasable`, qui vaut `always` sur tout le catalogue rattaché à un fournisseur — c'est-à-dire « commandable même à stock zéro ». Toute la vente sur approvisionnement était donc bloquée.

**Règles**

1. **Ne jamais comparer `stock` à la main pour décider d'un ajout au panier.** Utiliser `canBeFulfilledAtQuantity(int $quantity)` du contrat `Lunar\Base\Purchasable`, qui applique le mode d'achat : `always` → toujours acceptable, `in_stock` → borné par `getTotalInventory()` (`stock + backorder`).
2. **Un badge de disponibilité ne doit jamais promettre plus que ce que le panier accepte.** `Pko\Storefront\Support\VariantAvailability::for($variant)` retourne `['tone', 'label', 'orderable']` et centralise la règle :

   | Situation | Libellé |
   |---|---|
   | stock > 5 | En stock |
   | 0 < stock ≤ 5 | Stock limité |
   | stock = 0 et `canBeFulfilledAtQuantity(1)` | Sur commande |
   | stock = 0 et non approvisionnable | **Épuisé** |

   Le cas « Épuisé » n'existait pas : une variante `in_stock` à zéro s'annonçait « Sur commande » puis se faisait refuser. Toute nouvelle surface affichant un statut de stock doit passer par ce helper plutôt que de retester `stock > 0`.

**Placement du message d'erreur** — sur la carte produit, le bouton d'ajout vit dans une colonne `shrink-0` alignée en bas avec le prix : un bloc d'erreur en flux y élargit la colonne et recouvre le prix. En mode `compact`, le bloc est donc sorti du flux et ancré **au-dessus** du bouton (`absolute bottom-full right-0`). Ancrer vers le bas ne marche pas : l'`<article>` de la carte est en `overflow-hidden` et rognerait le message. En page produit (mode normal), le bloc reste en flux sous le bouton.

Tests : `tests/Feature/Storefront/AddToCartAvailabilityTest` (ajout d'un produit sur commande, refus au-delà du stock en `in_stock`, acceptation à la limite, cohérence des quatre libellés de badge).

### Checkout — pays par défaut et adresse déjà connue (2026-07-31)

**Pays pré-sélectionné.** `CheckoutPage::emptyAddress()` posait `Country::orderBy('name')->value('id')` sous le commentaire « pays de la boutique » : en pratique le **premier pays de la table par ordre alphabétique**, soit l'Afghanistan — affiché en écriture native (`$country->native`) dans le `<select>`, ce qui rendait le symptôme d'autant plus déroutant.

- Nouveau réglage `config('storefront.country')` (ISO 3166-1 alpha-2), env `STOREFRONT_COUNTRY`, repli sur `SHIPPER_COUNTRY` puis `FR`. Pas de « France » en dur dans le code : la boutique reste réutilisable (cf. CLAUDE.md §3.0).
- `getCountriesProperty()` trie désormais sur `native`, c'est-à-dire sur ce que le `<select>` affiche réellement. Le tri sur `name` (anglais) produisait une liste d'apparence aléatoire.

**Adresse déjà connue → récapitulatif direct.** Le pré-remplissage depuis le profil client (`prefilledAddress()`) alimentait le formulaire mais n'enregistrait rien sur le panier : `determineCheckoutStep()` maintenait donc l'étape « adresse de livraison » et le client devait revalider un formulaire déjà rempli.

`autoConfirmPrefilledAddress()` (appelée en `mount()`) pose l'adresse sur le panier **uniquement si tous les champs requis sont présents** — prénom, nom, ligne 1, ville, code postal, pays et un e-mail valide. Au moindre manque, le formulaire s'affiche comme avant. `shippingIsBilling` étant vrai par défaut, l'adresse de facturation est copiée dans la foulée, sinon le client enchaînait sur l'étape suivante avec les mêmes données à ressaisir. Le bouton « Modifier » du récapitulatif reste le chemin de correction.

Tests : `CheckoutBindingTest` (le jeu de pays inclut l'Afghanistan pour prouver que le défaut ne vient pas du premier enregistrement), `CheckoutPrefilledAddressTest` (profil complet → récapitulatif, profil incomplet → formulaire, retour en modification).
