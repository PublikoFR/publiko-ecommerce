# Admin Filament — navigation, produits, médiathèque

Réorganisation de la navigation Filament, liste + édition unifiée produits, global search.

## Navigation admin — réorganisation et Media Library

### Problème initial

L'admin Lunar enregistre ~20 resources dans 3 groupes anglais (`Catalog`, `Sales`, `Settings`). Le projet définissait des groupes français (`Catalogue`, `Commandes`, `Configuration`) dans `AppServiceProvider`, mais sans traductions FR pour Lunar → les resources Lunar se retrouvaient dans des groupes anglais distincts des groupes français de l'app.

### Solution : traductions FR Lunar + sous-classes navigation + reflection swap

**Traductions** : `lang/vendor/lunarpanel/fr/global.php` mappe `catalog→Catalogue`, `sales→Commandes`, `settings→Configuration`. Les resources Lunar tombent maintenant dans les bons groupes français.

**Sous-classes** : 4 resources dans `app/Filament/Resources/Pko*Resource.php` étendent les resources Lunar `ProductTypeResource`, `ProductOptionResource`, `AttributeGroupResource`, `CollectionGroupResource` pour changer uniquement `getNavigationGroup()` → `'Paramètres catalogue'`. `ProductTypeResource` retire aussi `getNavigationParentItem()` (était imbriqué sous "Produits").

**Reflection swap** : `LunarPanelManager::$resources` est `protected static` sans setter. `AppServiceProvider::swapLunarResources()` utilise `ReflectionProperty` pour substituer les 4 classes **avant** `register()`. Fragile en cas de changement interne Lunar — à surveiller lors des mises à jour.

### Navigation cible

| Groupe | Entrées | Usage |
|--------|---------|-------|
| **Catalogue** | Produits, Medias, Marques, Catégories, Caractéristiques | Quotidien |
| **Paramètres catalogue** (collapsed) | Types de produits, Options de produits, Groupes d'attributs, Groupes de collections | Setup initial |

**TreeManager** : anciennement 1 entrée nav, désormais 2 (`Catégories` et `Caractéristiques`) via `getNavigationItems()` retournant 2 `NavigationItem` avec query param `?tab=categories|features`. Toggle 3 modes sur la page (catégories seules, features seules, les deux).

**UX header actions** : le tab switcher (catégories / caractéristiques / les deux) est implémenté comme des `Action` objects dans `getHeaderActions()` avec des closures dynamiques `->color(fn (): string => $this->activeTab === 'xxx' ? 'primary' : 'gray')`. Raison : les boutons vivent dans la barre d'en-tête Filament natif, pas dans un composant Blade custom. Le bouton « Réparer l'arbre » et les actions de maintenance sont regroupés dans un `ActionGroup` dropdown (icône "...") pour désencombrer la barre.

**Export/Import** : les boutons Export/Import sont placés dans le `headerEnd` slot de chaque `<x-filament::section>` (catégories et caractéristiques), pas dans les header actions globaux. Chaque section a ses propres boutons contextuels.

**`switchTab()` et cache Filament** : Filament cache les header actions pendant `bootedInteractsWithHeaderActions()`. Un simple `$set` ou `$this->activeTab = ...` ne suffit pas à rafraîchir les couleurs des boutons tab. La méthode `switchTab()` vide manuellement `$this->cachedHeaderActions = []` puis rappelle `$this->cacheHeaderActions()` pour forcer la réévaluation des closures `color()`.

**Layout côte-à-côte** : le mode `both` utilise un `style="grid-template-columns: repeat(2, minmax(0, 1fr))"` inline au lieu d'une classe Tailwind (`grid-cols-2`). Raison : Tailwind JIT ne résout pas les classes dynamiques générées par Livewire morph (`@if($activeTab === 'both') class="grid-cols-2" @endif` n'est pas scanné par le compilateur JIT). Le style inline + media query CSS `@media (max-width: 1023px)` gère le responsive.

**SortableJS cross-level drag** : les listes catégories racine (`data-sortable="collections"`) et enfants (`data-sortable="collection-children"`) partagent le même groupe SortableJS `{ name: 'collections-tree', pull: true, put: true }`. Cela permet le reparenting drag-and-drop entre niveaux (racine ↔ enfant, enfant ↔ autre parent). Même pattern côté valeurs de caractéristiques avec le groupe `features-values`.

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

- Subclasse `Lunar\Admin\Filament\Resources\ProductResource` via le pattern `swapLunarResources()` déjà en place — **pas** de Filament Resource custom (cohérent avec la règle §3.1.9 du `CLAUDE.md`).
- Sous-navigation Lunar masquée (`getDefaultSubNavigation() => []`).
- Page Livewire unique (`EditProductUnified`) avec état plat + mini Filament Form embarqué uniquement pour le `MediaPicker` (Pko).
- Persistance : transaction unique — attributs, prix (y compris paliers B2B natifs Lunar via `min_quantity`), variantes, collections, tags (job Lunar `SyncTags`), features (CatalogFeatures), associations (cross-sell).
- Caractéristiques techniques : source = `pko_feature_families` / `pko_feature_values` (package CatalogFeatures), pas `attribute_data` Lunar.
- Historique : `spatie/laravel-activitylog` (déjà actif sur les modèles Lunar via trait `LogsActivity`).
- Pas d'autosave : save explicite uniquement. Indicateur visuel basé sur `$isDirty` Livewire.

---

