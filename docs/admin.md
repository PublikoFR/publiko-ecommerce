# Admin Filament — navigation, produits, médiathèque

Réorganisation de la navigation Filament, liste + édition unifiée produits, global search.

## Navigation admin — réorganisation et Media Library

### Problème initial

L'admin Lunar enregistre ~20 resources dans 3 groupes anglais (`Catalog`, `Sales`, `Settings`). Le projet définissait des groupes français (`Catalogue`, `Commandes`, `Configuration`) dans `AppServiceProvider`, mais sans traductions FR pour Lunar → les resources Lunar se retrouvaient dans des groupes anglais distincts des groupes français de l'app.

### Solution : traductions FR Lunar + sous-classes navigation + reflection swap

**Traductions** : `lang/vendor/lunarpanel/fr/global.php` mappe `catalog→Catalogue`, `sales→Commandes`, `settings→Configuration`. Les resources Lunar tombent maintenant dans les bons groupes français.

**Libellés de slugs** : liste et fiche commande Lunar lisent déjà `config('lunar.orders.statuses.*.label')`. La vue publiée `resources/views/vendor/lunarpanel/infolists/components/transaction.blade.php` traduit le driver et le statut de paiement via `payment_driver_label()` / `payment_status_label()`. Ne jamais modifier `vendor/`.

**Sous-classes** : 4 resources dans `app/Filament/Resources/Pko*Resource.php` étendent les resources Lunar `ProductTypeResource`, `ProductOptionResource`, `AttributeGroupResource`, `CollectionGroupResource` pour changer uniquement `getNavigationGroup()` → `'Paramètres catalogue'`. `ProductTypeResource` retire aussi `getNavigationParentItem()` (était imbriqué sous "Produits").

**Reflection swap** : `LunarPanelManager::$resources` est `protected static` sans setter. `AppServiceProvider::swapLunarResources()` utilise `ReflectionProperty` pour substituer les 4 classes **avant** `register()`. Fragile en cas de changement interne Lunar — à surveiller lors des mises à jour.

### Navigation cible

La structure complète du menu est pilotée par le package **`pko/lunar-admin-nav`** (cf. [packages/admin-nav.md](packages/admin-nav.md)) via `NavigationBuilder` injecté dans le panel. Elle remplace le `->navigationGroups([...])` statique historiquement déclaré dans `AppServiceProvider`.

Structure actuelle :

| Section | Contenu |
|---------|---------|
| **Pilotage** (sans label, en tête) | Tableau de bord, Commandes (avec badge), Expédition, Clients — raccourcis vers les Resources natives |
| **Catalogue** | Produits, Marques, Catégories, Caractéristiques |
| **Paramètres catalogue** (collapsed) | Types de produits, Options de produits, Groupes d'attributs, Groupes de collections, Catégories de documents, Tags |
| **Ventes & Clients** | Groupes de clients, Réductions, Abonnés newsletter, Fidélité (hub) |
| **Contenu** | Page d'accueil (hub), Contenus, Types de contenus |
| **Général** (collapsed) | Personnel, Rôles, Configurations LLM |
| **Imports et Données** (collapsed) | Imports, Configurations d'import, Activités |
| **Boutique** (collapsed) | Paramètres storefront, Magasins, Canaux, Langues |
| **Paiement & Expédition** (collapsed) | Devises, Taxes (Cluster Lunar → 1 entrée menu avec sub-nav on-page 3 items), Stripe |

**Sub-navigation on-page (droite)** : deux sections consolidées en 1 entrée menu + sub-nav Filament native côté droit — **Expédition** (6 items : Méthodes / Zones / Exclusion / Envois transporteurs / Chronopost / Colissimo, accessibles depuis le raccourci Pilotage) et **Taxes** (Cluster Lunar, 3 items : Zones / Classes / Taux, dans le groupe Paiement & Expédition). Swap Pko* des Resources Lunar requis — cf. `docs/packages/admin-nav.md`.

**Hubs à onglets** : Fidélité (`/admin/fidelite`) et Page d'accueil (`/admin/page-accueil`) fusionnent respectivement 4 et 3 entrées jadis listées séparément. Navigation inter-onglets sans rechargement complet (Livewire partial render, query string `?tab=`). URLs originales des Resources (loyalty-tiers, home-slides, etc.) restent accessibles mais non listées au menu.

**Raccourcis Pilotage** : Dashboard, OrderResource, CarrierShipmentResource, CustomerResource apparaissent uniquement dans la section Pilotage (pas de doublon dans un groupe métier). Les raccourcis utilisent `NavigationItem::url()` pointant vers la Resource native → aucune URL n'a changé.

**Liste clients (`PkoCustomerResource::getDefaultTable`)** : colonnes personnalisées via override de `getDefaultTable` (appel `parent` puis manipulation), pensées pour tenir sur un écran étroit. Colonnes affichées :
- **Client** : nom + prénom fusionnés dans une seule colonne (`TextColumn::make('first_name')->html()` + `formatStateUsing`), avec l'**e-mail affiché sous le nom** (empilement `flex flex-col`, e-mail en **texte simple**). La cellule reste cliquable vers la **fiche client** (lien de ligne `recordUrl` posé par `CustomerProfileExtension`) — on ne met **pas** de `<a mailto>` dans la cellule : la ligne étant déjà un `<a>`, une ancre imbriquée = HTML invalide qui cassait l'alignement (gros décalage à gauche). L'envoi de mail passe par l'action **« Envoyer un e-mail »** du dropdown (`Action->url('mailto:…')`, visible si le client a un e-mail). Recherche globale étendue à prénom / nom / **e-mail** (relation `users`) via `->searchable(query: …)` avec `orWhereHas('users')`.
- **Société**, **Département** (2 premiers chiffres de `pko_postcode`).
- **Groupes** : 3 premiers badges puis « … de plus » (`->badge()->limitList(3)`), liste complète en tooltip.
- **Statut** : ajouté par-dessus par `CustomerProfileExtension::extendTable` (après le swap).

Colonnes Lunar retirées : identifiant fiscal (`tax_identifier`) et référence du compte (`account_ref`). Filtre **Département** (`SelectFilter`, options = préfixes 2-chiffres distincts présents en base) en plus du filtre groupe. Rendu couvert par `CustomerListRenderCheckTest`.

**Actions de ligne en dropdown** : toutes les actions (`ViewAction`, `EditAction`, **« Envoyer un e-mail »** (mailto, si e-mail), impersonation) sont regroupées dans un `ActionGroup` (dernière colonne, icône « … ») pour gagner de la place. Un clic sur la ligne ouvre l'édition (`recordUrl` posé par `CustomerProfileExtension`). Le traitement RGPD **« Supprimer (RGPD) »** (`CustomerAnonymizeExtension`) est disponible en **row action** (danger) **et** en bulk action ; il supprime physiquement le client s'il n'a aucune commande, sinon l'anonymise et le masque de la liste (cf. `docs/packages/storefront-b2b.md`).

**Impersonation client** : action « Se connecter en tant que » (dans le dropdown) — connecte l'admin sur le **guard front `web`** (provider `users`) avec l'utilisateur du client puis redirige vers `/`. Le guard staff admin reste inchangé (guards distincts). Pas de bouton retour : on quitte l'impersonation via la déconnexion front normale. Visible uniquement si le client a un utilisateur rattaché.

Le login passe **obligatoirement** par `Pko\CustomerAuth\Actions\ImpersonateCustomerUser`, jamais par un `Auth::guard('web')->login()` direct. Raison : pendant une requête Filament, le panel Lunar bascule le guard **par défaut** sur `staff`, et les listeners branchés sur l'événement `Login` (`Lunar\Listeners\CartSessionAuthListener` → `CartSession::current()`) résolvent l'utilisateur via ce guard par défaut, pas via celui qui a émis l'événement. Sans bascule temporaire, le listener récupère le Staff connecté et appelle `Staff::carts()` → `BadMethodCallException`, HTTP 500. L'action bascule donc le guard par défaut sur `web` le temps du login puis restaure l'ancien (`try/finally`). Même précaution à prendre pour tout futur login front déclenché depuis l'admin.

**Bypass du gate pro** (décision produit) : l'action pose en session `ProAccess::IMPERSONATOR_SESSION_KEY` (= id du staff). `ProAccess::denialReason()` retourne alors `null` quel que soit l'état du compte : un admin peut faire du support sur un client `pko_status = pending` ou dont l'e-mail n'est pas confirmé, cas où le client lui-même serait renvoyé sur `/connexion` par `RequireProCustomer`. Contrepartie assumée : **ce que voit l'admin n'est pas ce que voit le client** — la modale de confirmation le rappelle.

Le bypass est doublement conditionné (`ProAccess::isImpersonating()`) : flag de session **et** session `staff` toujours authentifiée. Il meurt donc avec la session admin et ne peut pas survivre à une déconnexion du panel ; la déconnexion front (`/deconnexion` → `session()->invalidate()`) l'efface aussi.

Régressions couvertes par `tests/Feature/CustomerAuth/ImpersonateCustomerUserTest.php` (crash `Staff::carts()`, bypass du gate, gate toujours fermé hors impersonation, mort du bypass avec la session staff).

**Gotcha Filament** : Filament 3 ne supporte pas les sous-groupes imbriqués persistants côté sidebar. La section Configuration du cahier des charges est matérialisée par 4 groupes collapsed adjacents (Général / Imports et Données / Boutique / Paiement & Expédition) plutôt qu'un groupe unique Configuration avec sous-sections.

**TreeManager** : anciennement 1 entrée nav, désormais 2 (`Catégories` et `Caractéristiques`) via `getNavigationItems()` retournant 2 `NavigationItem` avec query param `?tab=categories|features`. Toggle 3 modes sur la page (catégories seules, features seules, les deux).

**UX header actions** : le tab switcher (catégories / caractéristiques / les deux) est implémenté comme des `Action` objects dans `getHeaderActions()` avec des closures dynamiques `->color(fn (): string => $this->activeTab === 'xxx' ? 'primary' : 'gray')`. Raison : les boutons vivent dans la barre d'en-tête Filament natif, pas dans un composant Blade custom. Le bouton « Réparer l'arbre » et les actions de maintenance sont regroupés dans un `ActionGroup` dropdown (icône "...") pour désencombrer la barre.

**Export/Import** : les boutons Export/Import sont placés dans le `headerEnd` slot de chaque `<x-filament::section>` (catégories et caractéristiques), pas dans les header actions globaux. Chaque section a ses propres boutons contextuels.

**`switchTab()` et cache Filament** : Filament cache les header actions pendant `bootedInteractsWithHeaderActions()`. Un simple `$set` ou `$this->activeTab = ...` ne suffit pas à rafraîchir les couleurs des boutons tab. La méthode `switchTab()` vide manuellement `$this->cachedHeaderActions = []` puis rappelle `$this->cacheHeaderActions()` pour forcer la réévaluation des closures `color()`.

**Layout côte-à-côte** : le mode `both` utilise un `style="grid-template-columns: repeat(2, minmax(0, 1fr))"` inline au lieu d'une classe Tailwind (`grid-cols-2`). Raison : Tailwind JIT ne résout pas les classes dynamiques générées par Livewire morph (`@if($activeTab === 'both') class="grid-cols-2" @endif` n'est pas scanné par le compilateur JIT). Le style inline + media query CSS `@media (max-width: 1023px)` gère le responsive.

**SortableJS cross-level drag** : les listes catégories racine (`data-sortable="collections"`) et enfants (`data-sortable="collection-children"`) partagent le même groupe SortableJS `{ name: 'collections-tree', pull: true, put: true }`. Cela permet le reparenting drag-and-drop entre niveaux (racine ↔ enfant, enfant ↔ autre parent). Même pattern côté valeurs de caractéristiques avec le groupe `features-values`.

**Toggle activation catégorie** : chaque nœud de l'arbre dispose d'un bouton œil (actif) / œil barré (désactivé) → `wire:click="toggleCollectionEnabled($id)"`. La désactivation cascade automatiquement à tous les descendants (nestedset `_lft/_rgt`) et invalide le cache storefront nav. Nœuds désactivés rendus en `opacity-50` + badge « désactivée ». Le même toggle est accessible depuis la fiche d'édition Collection (ResourceExtension `CollectionEnabledExtension` → section « Visibilité »). Voir `docs/packages/storefront.md` pour l'impact côté front.

**FeatureFamilyResource** : `shouldRegisterNavigation()` retourne `false`. Resource toujours enregistrée (URLs actives), juste absente du sidebar.

### Media Library — `tomatophp/filament-media-manager`

Package retenu pour la gestion centralisée des médias (photos, vidéos) dans l'admin, style WordPress. Basé sur Spatie MediaLibrary (compatible Lunar qui l'utilise déjà).

- Dossiers et sous-dossiers
- Alt tags / titres / descriptions via custom properties Spatie
- Traductions FR : `lang/vendor/filament-media-manager/fr/messages.php`
- Config : `config/filament-media-manager.php`, `navigation_sort => 2`
- Tables : `folders`, `media_has_models`, `folder_has_models`

Packages écartés : `awcodes/filament-curator` (incompatible Spatie), `outerweb/filament-image-library` (système propre incompatible). Option premium `ralphjsmit/media-library-pro` non retenue pour le moment (budget).

### Médiathèque custom — `PkoMediaLibrary` (route `admin/mediatheque`)

La page Filament par défaut de `tomatophp/filament-media-manager` est conservée en backend (modèle `Folder`, tables) mais **remplacée par une page custom** `Pko\StorefrontCms\Filament\Pages\PkoMediaLibrary` (coquille fine qui monte le composant Livewire unifié `pko-media-library` — voir §Composant unifié `PkoMediaLibrary`). Layout WP-style, multi-sélection, lightbox, slide-over édition.

**Uploader optimiste WP-style** :
- Dropzone HTML custom (pas de FilePond) : `<label>` + `<input type="file" multiple>` + drag/drop natif
- Tuiles "en cours" injectées dans la grid via Alpine (`x-data="pkoMediaLibraryUploader()"`) avec preview locale (`URL.createObjectURL`) et spinner SVG circulaire basé sur l'event `progress` de Livewire
- Persistance : `$wire.upload('pendingUpload', file, finishCb, errorCb, progressCb)` → property `$pendingUpload` (trait `Livewire\WithFileUploads`) → méthode `persistPendingUpload(string $originalName)` qui fait `$folder->addMedia()->toMediaCollection()`
- Sécurité suppression dossier : `deleteFolder()` compte les médias (`model_type=Folder, model_id=$id`) et refuse si > 0
- Multi-sélection : property `$selectedMediaIds[]`, actions `bulkDeleteMedias()` / `confirmBulkMove()` (update `model_id` + `collection_name`, fichiers physiques inchangés car le path Spatie est basé sur l'id)

### Thème Filament custom — `resources/css/filament/admin/`

Le panel Lunar ne charge pas le CSS storefront (`resources/css/app.css`). Les classes Tailwind utilisées dans les views custom Filament (pages, plugins custom) ne sont donc pas compilées dans le bundle admin par défaut.

**Solution** : thème Filament dédié, enregistré via `->viteTheme('resources/css/filament/admin/theme.css')` dans `AppServiceProvider`.

**Structure atomique** (une règle : un module = un fichier) :
```
resources/css/filament/admin/
├── theme.css                      # entrée — imports vendor Filament + modules
├── tailwind.config.js             # preset Filament + scan des views admin et packages
└── modules/
    └── media-library.css          # styles de PkoMediaLibrary
```

- Pour modifier un module : éditer **uniquement** son fichier dans `modules/`
- Pour ajouter un module : créer `modules/<nom>.css` + ajouter `@import './modules/<nom>.css';` en tête de `theme.css` (contrainte CSS : tous les `@import` doivent précéder tout autre contenu)
- Ajouté à `vite.config.js` en 2ᵉ input aux côtés de `resources/css/app.css`

**Dépendances NPM ajoutées** (requises par le preset Filament) :
- `@tailwindcss/typography` (dev)
- `postcss-nesting` (dev)

---

## Tableau de bord Weklo (page d'accueil `/admin`)

Le dashboard Lunar (grille de widgets ApexChart) est **remplacé** par la page design
Weklo — importée depuis le Claude Design System (projet `Dashboard Weklo`) et portée
en Blade/Alpine/ApexCharts natif de la stack.

**Fichiers** :
| Fichier | Rôle |
|---|---|
| `app/Filament/Pages/WekloDashboard.php` | Page Filament, sous-classe de `Lunar\Admin\Filament\Pages\Dashboard`. Slug forcé à `dashboard` → route `filament.lunar.pages.dashboard` et URL `/admin` **inchangées** (la nav custom continue de pointer dessus). `$view` custom, `getWidgets()` vide. |
| `resources/views/filament/pages/weklo-dashboard.blade.php` | Vue design complète : KPI, mini-stats, graphes CA/statuts/catégories/régions/clients, tunnel, stock, devis, promos, dernières commandes. Alpine pilote période/filtres/recherche **100 % côté client** (aucun aller-retour Livewire → évite le crash ApexCharts ↔ Livewire, cf. `DisableBrokenChartsExtension`). ApexCharts chargé via CDN. |
| `app/Support/Dashboard/DashboardStats.php` | Service agrégeant les stats **réelles** depuis les tables Lunar (commandes, lignes, clients, variantes, paniers, remises, fidélité). `build($period)` retourne le payload d'une période ; la page pré-calcule les 4 périodes (`jour`/`7j`/`30j`/`12m`) et les sérialise pour Alpine. |
| `resources/css/filament/admin/modules/weklo-dashboard.css` | Tokens DS (forest/lime/neutrals/semantics) **scopés à `.wk-dash`** + `@font-face` Forno Waffle + IBM Plex Mono + helpers (hover, tooltip ApexCharts). |

**Swap de la page** : `AppServiceProvider::swapLunarPages()` remplace `Dashboard::class`
par `WekloDashboard::class` dans `LunarPanelManager::$pages` par réflexion (même pattern
que `swapLunarResources()`, doit tourner **avant** `LunarPanel::panel()->register()`).

**Métriques sans source réelle** : le schéma n'a ni analytics web ni prix d'achat, donc
**taux de conversion**, **étapes hautes du tunnel** (sessions / visiteurs / ajouts panier)
et **marge brute** sont générés en pseudo-aléatoire déterministe (LCG Park-Miller, stable
par période) et portent un badge **« stat à connecter »** (clé `simulated => true` dans le
payload). Tout le reste est branché en vrai. Pour connecter réellement ces 3 métriques :
brancher un tracking analytics (sessions/paniers) et stocker un prix d'achat sur la variante.

**Branding du panel** : `AppServiceProvider` applique `->brandLogo()`,
`->darkModeBrandLogo()`, `->favicon()`, `->brandLogoHeight('2.5rem')`,
`->font('Hanken Grotesk')` et `->colors([...])` (ramps forest = primary, lime = accent,
neutrals green-tinted = gray, + semantics).

Logos/favicon **configurables** depuis **Storefront → Paramètres** (page
`StorefrontSettings`), via les Settings `brand.logo` (clair), `brand.logo_dark`
(sombre) et `brand.favicon` (commun). Les closures du panel lisent ces Settings à
l'affichage (helpers `brand_logo()` / `brand_logo_dark()` / `brand_favicon()`, tous
résolus par `brand_media_url()`), avec repli sur les assets Weklo
(`public/img/weklo-lockup.png`, `public/img/weklo-mark.png`) et, pour le logo sombre
non renseigné, sur le wordmark « weklo » rendu en Forno Waffle blanc (`.wk-logo-dark`).

**Chrome du panel aligné sur la maquette** (module `weklo-dashboard.css`, styles hors
scope `.wk-dash` car rendus dans le layout Filament) : onglet sidebar actif (fond
forest-50 + barre lime à gauche + label forest), recherche globale de la topbar en
pastille arrondie (fond sunken) avec placeholder FR override
(`lang/vendor/filament-panels/fr/global-search.php`), et identité du staff connecté
(nom + rôle) à gauche de l'avatar via `renderHook(PanelsRenderHook::USER_MENU_BEFORE)`
→ `resources/views/filament/hooks/user-identity.blade.php`.

**Lien « Voir la boutique »** dans la topbar (à droite de la recherche globale) via
`renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER)` →
`resources/views/filament/hooks/view-shop.blade.php`. Pointe vers `url('/')` (front
storefront), ouvert dans un nouvel onglet. Masqué en dessous de `md`.

---


## Liste produits admin — colonnes personnalisées

`PkoProductResource::getTableColumns()` remplace la liste Lunar native par : **Image · Marque · Nom · Prix · Réf. · Stock · Catégorie principale**. Statut et Type de produit supprimés.

### Colonnes custom

- **Image** (`pko_thumbnail`) — premier media attaché via `pko_mediables` (`mediagroup='product'`, `position=0`). URL Spatie originale (pas de conversion `small` car les médias appartiennent à la médiathèque, pas à Lunar Product). Placeholder SVG inline (40×40) pour les produits sans image.
- **Prix** — base price du 1er variant (`customer_group_id = null`, `min_quantity ≤ 1`), formaté via `$price->price->formatted()`.
- **Catégorie principale** — 1ère collection attachée (`$product->collections->first()->translateAttribute('name')`).

### Gotcha Lunar — sous-classe ListProducts obligatoire

`Lunar\Admin\Filament\Resources\ProductResource\Pages\ListProducts` a `protected static string $resource = ProductResource::class` **hardcodé**. Le swap de resource au niveau panel (`swapLunarResources()`) ne suffit pas : Filament instancie la page et appelle `ProductResource::getTableColumns()` via ce `$resource`, **pas** `PkoProductResource::getTableColumns()`.

**Solution** : créer `PkoListProducts extends ListProducts` avec `$resource = PkoProductResource::class`, l'enregistrer via `getDefaultPages()['index']`. Late-static-binding résout ensuite les méthodes overridées correctement.

Ce pattern s'applique à toute personnalisation de list/view/manage pages Lunar : la sous-classe de Resource seule ne suffit pas, il faut aussi sous-classer la page qui déclare `$resource` en dur.

## Sticky footer admin — fix `min-h`

Le footer `sticky bottom-0` du form `EditProductUnified` se détachait quand on scrollait au-delà du contenu (comportement CSS normal : le sticky finit en bas de son parent). Fix : `min-h-[calc(100dvh-6rem)]` sur le `<form>` pour que le parent atteigne toujours le bas du viewport.

## Global search admin — produits

`PkoProductResource` override les 4 hooks Filament : `getGloballySearchableAttributes()` (variants.sku/ean/mpn, brand.name, tags.value), `getGlobalSearchEloquentQuery` (eager variants+brand), `getGlobalSearchResultTitle` (translateAttribute('name')), `getGlobalSearchResultDetails` (Marque + Réf. + Stock), `getGlobalSearchResultUrl` (redirige vers EditProductUnified).

## Page d'édition produit unifiée

Voir `docs/product-edit-unified-page.md`. En résumé :

- Subclasse `Lunar\Admin\Filament\Resources\ProductResource` via le pattern `swapLunarResources()` déjà en place — **pas** de Filament Resource custom (cohérent avec la règle `AGENTS.md` §3.1.6).
- Sous-navigation Lunar masquée (`getDefaultSubNavigation() => []`).
- Page Livewire unique (`EditProductUnified`) avec état plat + mini Filament Form embarqué uniquement pour le `MediaPicker` (Pko).
- Persistance : transaction unique — attributs, prix (y compris paliers B2B natifs Lunar via `min_quantity`), variantes, collections, tags (job Lunar `SyncTags`), features (CatalogFeatures), associations (cross-sell).
- Caractéristiques techniques : source = `pko_feature_families` / `pko_feature_values` (package CatalogFeatures), pas `attribute_data` Lunar.
- Historique : `spatie/laravel-activitylog` (déjà actif sur les modèles Lunar via trait `LogsActivity`).
- Pas d'autosave : save explicite uniquement. Indicateur visuel basé sur `$isDirty` Livewire.

## Champ produit — Frais de port offert (dropshipping)

Toggle "Frais de port offert" rendu directement dans la **page d'édition produit unifiée** (`EditProductUnified`, carte "Inventaire & expédition"), pas via une extension Lunar : la fiche produit est une page Livewire custom qui n'utilise pas le form Lunar standard, donc `ResourceExtension::extendForm` n'a aucun effet dessus. Le champ est une prop Livewire `freeShipping` (chargée dans `mount()`, persistée dans `save()` sur `$product->pko_free_shipping`).

- Colonne source : `lunar_products.pko_free_shipping` (boolean, NOT NULL DEFAULT 0, index).
- ⚠️ Tout nouveau champ produit doit être ajouté à `EditProductUnified` + son template Blade ; une extension `extendForm` serait ignorée.
- Effet checkout : cf. [shipping.md §5.8](shipping.md#58-frais-de-port-offert-par-produit--dropshipping-2026-06).

## Champs produit — Logistique & transport (L3 2026-06)

Cinq champs ajoutés dans la carte « Inventaire & expédition » de `EditProductUnified` (même pattern props Livewire + mount + save) :

| Champ DB | Prop Livewire | Type | Description |
|---|---|---|---|
| `pko_logistics_class` | `logisticsClass` | `?string` | Classe A/B/C : A Standard, B Fournisseur (surcoût), C Volumineux/spécifique |
| `pko_franco_eligible` | `francoEligible` | `bool` | Le produit peut bénéficier du franco (coché par défaut) |
| `pko_transport_price_cents` | `transportPriceCents` | `?int` | Frais dédiés en centimes — affiché seulement si classe = C |
| `pko_quote_only` | `quoteOnly` | `bool` | Commande sur devis, sans paiement immédiat |
| `pko_supplier_id` | `supplierId` | `?int` | FK → `pko_suppliers.id` |

Traductions via `pko-shipping-common::admin.product.*` (lang `fr` dans `packages/pko/shipping-common/lang/fr/admin.php`).

---


## Visibilité catalogue par groupe client — sémantique inversée (opt-out)

**Règle : absence de ligne = visible.** Une ligne dans
`lunar_collection_customer_group` / `lunar_customer_group_product` n'existe que
pour **restreindre** un groupe.

### Pourquoi on s'écarte de Lunar

Lunar est en opt-in : le trait `HasCustomerGroups` (`vendor/lunarphp/core/src/Base/Traits/HasCustomerGroups.php:29`)
sème, à la création de **chaque** collection et de **chaque** produit, une ligne
pour **tous** les groupes clients existants, à `enabled = visible = $group->default`
— donc à `false` pour tout groupe non défaut.

Trois conséquences, toutes indésirables ici :

1. **Asymétrie silencieuse.** Le semis est unidirectionnel : créer un
   `CustomerGroup` ne sème rien (aucun hook `boot` dans `Lunar\Models\CustomerGroup`).
   Un groupe créé *après* l'import du catalogue n'a donc aucune ligne — et le
   scope Lunar (`whereHas` + `enabled OR visible`) lui masquerait la totalité du
   catalogue, sans le moindre message.
2. **Volume mort.** 494 collections × 7 groupes = 3 458 lignes ne portant aucune
   décision humaine, plus autant par produit.
3. **Suppression de groupe impossible.** Le garde-fou `CustomerGroupGuard`
   comptait ces lignes comme des références : tout groupe neuf était réputé
   « encore utilisé (494 collection(s)) » et devenait indéracinable.

Choix produit associé : **tous les groupes clients voient tout le catalogue**,
seuls les **tarifs** diffèrent (`lunar_prices`). Le storefront ne filtre donc
jamais par groupe client.

### Implémentation

| Élément | Rôle |
|---|---|
| `app/Support/CatalogAvailability.php` | Sémantique centrale : `purge()`, `forget()`, `restrictionCount()` |
| `app/Observers/CatalogAvailabilityObserver.php` (+ `Collection`/`Product`) | Efface, au `created`, les lignes que le trait vient de semer |
| `app/Console/Commands/PruneCatalogAvailability.php` | `pko:catalog-availability:prune` — rattrapage **ciblé**, dry-run par défaut, idempotent |
| `app/Support/CustomerGroupGuard.php` | Ne compte que les lignes **restrictives** pour ces deux pivots |
| `app/Filament/Extensions/CustomerGroupAvailabilityExtension.php` | Ajoute `DetachAction` à l'onglet Disponibilité |

### Rattrapage — ciblage, jamais de purge

La commande ne vide **pas** la table : elle ne supprime que les lignes portant la
**signature du semis automatique**, pour qu'une restriction saisie à la main
survive. Les trois marqueurs, tous requis (`CatalogAvailability::seededRows()`) :

1. tous les flags sont **uniformes** (tous à 0, ou tous à 1) — le trait les écrit
   tous à `$customerGroup->default`, donc identiques entre eux ;
2. `ends_at` est `NULL` — le trait ne pose jamais de date de fin ;
3. `starts_at` **et** `created_at` tombent dans la même seconde (± 5 s) que la
   création du parent — le semis est déclenché par le `created` du modèle, alors
   qu'une saisie humaine intervient nécessairement plus tard.

Le marqueur 3 est le discriminant : il sépare une ligne « tous flags à false »
semée à l'import d'une restriction identique posée volontairement.

⚠️ Les flags sont comparés **entre eux**, jamais à `customer_group.default` : le
groupe par défaut change dans le temps. Sur dev, 258 lignes semées le 10/07 —
quand « Particuliers » était le défaut — portaient des flags à 1 alors que le
défaut est devenu « Pro » depuis ; les comparer au défaut actuel les faisait
passer à tort pour des décisions humaines. Une ligne aux flags **panachés**
(ex. `visible=1, purchasable=0`) est en revanche forcément une saisie : le trait
ne produit jamais ça, elle est conservée.

Sans `--apply`, la commande se contente de rapporter (total / semées / conservées).
Le comportement est verrouillé par
`test_prune_removes_seeded_rows_but_keeps_manual_restrictions()`, vérifié
discriminant : remplacer le ciblage par une purge globale le fait échouer.

**Ligne restrictive** = au moins un flag de disponibilité à `0` (`enabled`,
`visible`, plus `purchasable` pour les produits). Une ligne dont tous les flags
sont à `1` est redondante avec le défaut implicite : elle ne bloque pas la
suppression du groupe, mais reste détachée avant `delete()` (FK `NO ACTION`).

### Pièges

- **Ordre des listeners.** L'observer doit se déclencher **après** celui du trait,
  sinon le sync réinsère les lignes et le correctif devient un no-op silencieux.
  L'enregistrement passe donc par `Model::observe()` (qui fait `new static` et
  boote le modèle, donc branche le trait en premier) et **jamais** par
  `Model::created()`. Verrouillé par
  `CatalogAvailabilityTest::test_creating_a_collection_seeds_no_rows()` — test
  vérifié discriminant (il échoue si l'observer est retiré).
- **Le scope Lunar `customerGroup()` suppose l'inverse** et ne doit pas être
  utilisé tel quel : il exclut ce qui n'a pas de ligne. S'il fallait un jour
  filtrer le storefront par groupe, il faudrait notre propre scope
  (`whereDoesntHave` sur les lignes restrictives). Ce n'est pas au programme.
- **Discounts et shipping methods restent en opt-in.** Le même trait les couvre,
  mais là le rattachement explicite est voulu : une remise ne s'applique qu'aux
  groupes qu'on lui a attachés. Ces pivots restent intégralement bloquants dans
  `CustomerGroupGuard::REFERENCE_TABLES`.
- **Pas de `DetachAction` chez Lunar.** Le `CustomerGroupRelationManager` n'expose
  qu'`AttachAction` + `EditAction` — cohérent en opt-in, bloquant chez nous
  (une restriction posée serait irréversible depuis l'admin). D'où l'extension.

## Groupes clients et accès pro — strictement indépendants

**Un groupe client ne conditionne JAMAIS la connexion.** Les groupes Lunar sont
des étiquettes de tarification et de visibilité catalogue, que l'admin déplace
librement depuis la fiche client — retirer « Nouveau client » une fois le compte
qualifié, le basculer dans son groupe métier. C'est un geste d'administration
courant, il ne doit avoir aucun effet sur l'authentification.

`ProAccess::denialReason()` a longtemps exigé l'appartenance au groupe par
défaut (`customer-auth.default_customer_group_handle`, « nouveau-client »).
Conséquence signalée en production : **retirer ce groupe d'une fiche client
rendait le compte inconnectable** (« Accès réservé aux comptes
professionnels »), jusqu'à ce qu'on le lui remette. Le gate ne regarde donc plus
les groupes du tout.

Critères d'accès pro restants, dans l'ordre où ils sont évalués :

| critère | motif de refus |
|---|---|
| session d'impersonation admin | — (bypass, cf. plus haut) |
| session `JustRegistered` (auto-login post-inscription) | — (bypass) |
| aucun `Customer` rattaché à l'utilisateur | compte non rattaché à une société |
| `pko_status = banned` | compte suspendu |
| `pko_status = pending` | e-mail non confirmé (lien renvoyé) |

Le rattachement au groupe par défaut reste posé à l'inscription
(`RegisterProCustomer`) — un client sans groupe n'a pas de prix — et
`BackfillDefaultCustomerGroupCommand` sert toujours à rattraper les comptes
historiques. Mais c'est désormais une question de **tarification**, plus jamais
d'accès. Régression couverte par `ProAccessRedirectTest`
(`test_customer_keeps_access_after_default_group_is_removed`,
`test_customer_in_another_group_only_keeps_access`).

## Colonne « Type de client » de la liste des commandes — « Retour » à tort

La colonne affiche `lunar_orders.new_customer`, calculé par le job Lunar
`MarkAsNewCustomer` : nouveau client ⟺ aucune commande **placée** antérieurement
avec la même adresse e-mail de facturation. **Ce flag n'a aucun rapport avec le
groupe client « Nouveau client »** — l'homonymie a fait croire à un lien de
cause à effet.

Le job est dispatché **dans la transaction** de `Lunar\Actions\Carts\CreateOrder`.
Avec `after_commit = false` (défaut Laravel), il partait en file immédiatement :
un worker rapide le consommait avant le commit, `Order::find()` ne trouvait rien
et le job sortait **silencieusement** — sans échec, donc sans retry. La colonne
gardait alors sa valeur par défaut (`false`) et affichait « Retour » pour un
primo-commandant. Symptôme intermittent, au gré de la course entre le worker et
le commit.

Correction : `config/queue.php` pose `'after_commit' => env('QUEUE_AFTER_COMMIT', true)`
sur les connexions `redis` et `database`. Rattrapage de l'historique :

```bash
make artisan CMD='lunar:orders:sync-new'
```

Couvert par `tests/Feature/Orders/NewCustomerFlagTest.php`, qui vérifie à la fois
le calcul du flag et le garde-fou de configuration.

**Libellés du badge** : Lunar affichait « Nouveau » / **« Retour »**, ce dernier se
lisant comme un retour marchandise ou une demande SAV. Override partiel dans
`lang/vendor/lunarpanel/fr/customer.php` (`table.new` / `table.returning`) →
« Nouveau » / « Récurrent », aligné sur le filtre FR de Lunar déjà intitulé
« Nouveau / Récurrent ». Laravel fusionne ce fichier par-dessus celui du package
(`array_replace_recursive`), inutile de recopier tout le fichier.

## Suppression d'un groupe client — cascade dans les deux sens

**Principe : une liaison se nettoie quel que soit le côté supprimé.**

### Sens objet → groupe (déjà assuré par Lunar)

| suppression de… | détache le groupe | où |
|---|---|---|
| réduction | ✓ | `DiscountObserver::deleting()` |
| méthode de livraison | ✓ | `ShippingMethod::deleting()` |
| produit | ✓ | `ProductObserver::deleting()` |
| catégorie | ✓ (sous-arbre compris) | `CollectionObserver` + `App\Observers\CollectionDeleteObserver` |

Couvert par `CustomerGroupCascadeTest` pour détecter une régression d'un upgrade Lunar.

### Sens groupe → objet (`App\Support\CustomerGroupDeletionImpact`)

Toutes les FK vers `lunar_customer_groups` sont en `NO ACTION` : sans cascade
explicite, le delete plante en `1451`. À la suppression d'un groupe :

| élément | traitement |
|---|---|
| clients | réattribués au groupe par défaut |
| visibilité catalogue | détachée |
| réductions, livraison, zones de taxe | détachées |
| **tarifs** | **supprimés** |

**Les tarifs ne sont jamais détachés.** `lunar_prices.customer_group_id` est
nullable et un prix à `NULL` s'applique à **tous** les groupes : les détacher
publierait des tarifs négociés en prix public. Verrouillé par
`test_group_prices_are_deleted_never_nulled()`.

### Éléments orphelins — le choix revient à l'utilisateur

Si le groupe supprimé est le dernier auquel un élément est **activement** rattaché,
la modale le nomme et propose : *le supprimer aussi* ou *le conserver*. Conservé,
il reste en base mais ne s'applique plus à personne — le scope Lunar est un
`whereHas`, un objet sans groupe ne remonte dans aucune requête. On préfère poser
la question que de neutraliser en silence.

En suppression **de masse**, la question ne peut pas être posée par groupe : on
conserve (choix non destructif) et la notification indique combien d'éléments sont
devenus inactifs.

### Piège — les liaisons auto-semées faussent le comptage

`HasCustomerGroups` est monté sur `Discount` et `ShippingMethod` aussi : créer une
réduction sème une ligne par groupe existant, à `enabled = false`. Un comptage brut
ferait donc paraître **toute** réduction rattachée à **tous** les groupes.

`analyse()` ne considère donc que les liaisons **actives** (`enabled = true`). Une
ligne semée inactive ne rattache rien : ni partage, ni orphelin.

⚠️ Contrairement aux pivots catalogue, on ne purge pas ces lignes : `DiscountManager`
utilise réellement ce scope, `enabled = false` y a un sens fonctionnel (« cette
réduction ne s'applique pas à ce groupe »). C'est du vrai état, pas du bruit.

### Piège — supprimer un orphelin passe OBLIGATOIREMENT par Eloquent

Les modèles Lunar nettoient leurs dépendances dans `deleting()` :
`ShippingMethod` → `shippingRates`, `TaxZone` → `taxRates`, `Discount` →
`discountables`. Un `DB::table()->delete()` court-circuite ces observers et fait
planter la suppression en `1451` sur la table enfant :

```
Cannot delete or update a parent row: a foreign key constraint fails
(`lunar_shipping_rates`, CONSTRAINT `lunar_shipping_rates_shipping_method_id_foreign`)
```

La suppression des orphelins utilise donc `$model::find($id)?->delete()`.
Verrouillé par `test_deleting_an_orphan_shipping_method_cascades_to_its_rates()`,
vérifié discriminant (rétablir le `DB::table()` reproduit le 1451 à l'identique).

### Ce qui reste bloquant

Faute de cascade possible : le groupe par défaut Lunar, le groupe de l'inscription
professionnelle (`customer-auth.default_customer_group_handle`), et les restrictions
catalogue explicites.

## Fiche commande — extensions ManageOrder

Les champs métier custom sont ajoutés à la fiche commande Lunar via des `ResourceExtension`
attachées à `ManageOrder::class` dans `AppServiceProvider::LunarPanel::extensions()`.
Deux types de hooks : `headerActions()` pour les actions d'en-tête, et les hooks statiques
d'infolist de `ManageOrder` (`extendOrderSummarySchema`, `extendInfolistAsideSchema`…) pour
afficher une information. Une information ne doit **pas** être un faux bouton désactivé.

### Menu « Actions » de l'en-tête

Extension : `App\Filament\Extensions\OrderHeaderActionsDropdownExtension`, enregistrée **en
dernier** dans la liste `ManageOrder::class` (elle reçoit les actions de toutes les extensions
précédentes). Elle regroupe toutes les actions d'en-tête (Lunar + PKO) dans un `ActionGroup`
unique ; les sous-groupes (ex. avoirs Pennylane) deviennent des sections du menu
(`dropdown(false)`). Les badges informatifs nommés `*_badge` (commande scindée) restent hors menu.
Les tests `callAction('<nom>')` fonctionnent toujours : Filament met en cache les actions groupées.

### Nom du chantier (`pko_site_name`)

Affiché dans le bloc « vue d'ensemble » de la colonne latérale (ligne « Chantier »), uniquement
quand `order->pko_site_name` est renseigné — cf. `OrderPageLayoutExtension::overview()`.
L'ancienne `OrderSiteNameExtension` (hook `extendOrderSummarySchema`) a été supprimée : le résumé
Lunar n'est plus rendu. Champ nullable string(255) sur `lunar_orders`, ajouté par la migration
`2026_09_12_000001_add_pko_site_name_to_lunar_orders.php`.

- **Saisie** : checkout step 4 (payment), champ optionnel persisté immédiatement dans `cart.meta['pko_site_name']` via `CheckoutPage::updatedSiteName()`.
- **Propagation cart → order** : pipeline `App\Pipelines\Orders\PropagateCartSiteNamePipeline` (ajouté avant `MarkQuoteOrderAwaitingQuote` dans `config/lunar/orders.php`).
- **Split quote** : `CreateSplitQuoteOrder` copie le champ depuis la commande payante directement dans l'INSERT (bypass pipeline).
- **Modification client** : `Pko\Account\Livewire\OrderDetailPage::saveSiteName()` — re-guard ownership obligatoire dans la méthode (pas uniquement dans mount).
- **Affichage client** : ligne « Chantier : <nom> » sous la référence dans la liste « Mes commandes » (`OrdersPage`) et les commandes récentes du tableau de bord (`Dashboard`), uniquement si renseigné.

### Mise en page de la fiche (`OrderPageLayoutExtension`)

Extension : `App\Filament\Extensions\OrderPageLayoutExtension`. Colonne principale réordonnée
via `extendInfolistSchema`, tous les blocs pliables (`collapsible()` + `persistCollapsed()` :
l'état plié est mémorisé par le navigateur, clé = `id` de la section) :

1. **Produits dans la commande** — lignes Lunar, puis sur une grille 5 colonnes : notes client
   (2/5 ≈ 40 %) et totaux (3/5 ≈ 60 %). Les totaux sont rendus par
   `resources/views/filament/orders/order-totals.blade.php` (ligne sur deux grisée), à partir de
   `OrderPageLayoutExtension::totalsRows()`. Réduction et remboursement n'apparaissent que s'ils
   sont non nuls ; « Payé » / « Remboursé » ne comptent que les transactions `success`.
2. **Livraison** — mode de livraison, instructions de livraison, point relais, un encart par
   envoi `CarrierShipment` (n° de suivi copiable, statut, étiquette, lien de suivi La Poste
   — même URL que l'e-mail d'expédition —, fiche de l'envoi), bordereau de remise du jour, et
   l'adresse de livraison.
3. **Transactions** (`extendTransactionsInfolist`), suivies de l'adresse de facturation.

Colonne latérale (`extendInfolistAsideSchema`) : le nom du client (entrée `customer`) et le
résumé Lunar (section sans titre) sont remplacés par un bloc unique « vue d'ensemble »
(`resources/views/filament/orders/order-overview.blade.php`, données de
`OrderPageLayoutExtension::overview()`) : référence copiable + badge de statut cliquable
(`wire:click="mountAction('update_status')"` → modale Lunar de changement de statut, action
d'en-tête toujours appelable bien que regroupée dans le menu Actions), date FR
(« Passée le 19/07/2026 à 10:34 »), client (nouveau/récurrent · nombre de commandes passées,
bouton « Fiche client »), puis Chantier / Réf. client / Canal seulement s'ils sont renseignés
— Canal masqué tant qu'il n'existe qu'un seul canal. Les hooks `extendOrderSummary*` de Lunar
ne s'appliquent donc plus. Les deux adresses en sortent, le bloc
« Étiquettes » (tags Lunar) est supprimé — inutile, et son autocomplétion proposait les tags
produits — et l'**historique** (`extendTimelineInfolist`, timeline Lunar enveloppée dans une
section pliable) prend sa place, pour avoir la chronologie à côté du détail.

Lignes de commande : `App\Filament\Extensions\OrderLinesTableExtension`, keyée sur
`OrderItemsTable::class` (hook statique `extendOrderLinesTableColumns` du composant Livewire,
pas de la page). Affiche `quantité x prix unitaire` ; Lunar affichait `quantité @ sous-total de
la ligne`, qui se lit à tort comme un prix unitaire dès que la quantité dépasse 1.

Arbitrages :

- Le groupe d'alertes Lunar (`shouts`) ouvre la colonne principale ; vide, il consomme quand
  même un espacement de grille et décale la colonne sous la colonne latérale. Il est masqué via
  le hook `extendsInfolist` (avec un « s ») quand aucune alerte n'est visible.

- Lunar construit la colonne principale dans un ordre fixe (`[0]` expédition, `[1]` lignes,
  `[2]` totaux, `[3]` transactions, `[4]` historique) ; l'extension s'appuie sur ces positions,
  remplace `[0]` et `[2]`, déplace `[4]` dans l'aside, et garde en fin de colonne tout
  composant ajouté au-delà.
- Les adresses sont les sections Lunar d'origine (`getShippingAddressInfolist()` /
  `getBillingAddressInfoList()`), seulement déplacées : l'action « Modifier » est conservée.
  Elles sont retirées de l'aside par comparaison de leur titre traduit.
- Totaux en vue Blade plutôt qu'en entrées Filament : les lignes de port et de TVA sont des
  groupes imbriqués, un `nth-child` CSS ne peut pas alterner les fonds de façon fiable.
- Les actions d'un bloc `Actions` d'infolist sont chacune enveloppées dans un `ActionContainer`
  dont la clé vaut `{statePath}.{nom}Action` : c'est cette clé qu'attend `callInfolistAction()`
  en test (ex. `.download_label_12Action`), pas la clé du bloc `Actions`.
- Le menu d'en-tête « Expédition » (`OrderShipmentActionsExtension`) est conservé comme raccourci.

### Notes client (`pko_customer_notes`)

Note libre saisie par le client au checkout (bloc « Informations complémentaires », avec le nom
du chantier). Colonne `text` nullable sur `lunar_orders`
(`2026_09_18_000001_add_pko_customer_notes_to_lunar_orders.php`), max 2 000 caractères.

- **Pourquoi pas `orders.notes` de Lunar** : `notes` est envoyée telle quelle comme description
  de la facture Pennylane (`OrderToInvoiceMapper`) ; un message client n'a rien à y faire.
  `notes` reste affichée sous les notes client, uniquement si renseignée.
- **Flux** : identique au nom du chantier — `CheckoutPage::updatedCustomerNotes()` (HTML retiré)
  → `cart.meta['pko_customer_notes']` → `PropagateCartCustomerNotesPipeline` → colonne ;
  `CreateSplitQuoteOrder` la recopie sur la commande devis.
- **Affichage admin** : bloc « Produits dans la commande », texte échappé, retours à la ligne conservés.
