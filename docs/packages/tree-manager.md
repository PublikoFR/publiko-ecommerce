# Publiko Tree Manager — admin catégories + caractéristiques

### 7.ter.1 Objectif

Une seule page admin (`/admin/tree-manager`) qui remplace l'aller-retour entre le CRUD Collections de Lunar Admin et la Resource `FeatureFamily` du package `catalog-features`. Mêmes écrans, mêmes données, même expérience drag-n-drop des deux côtés avec modales CRUD live et import/export JSON par arbre.

### 7.ter.2 Anatomie

- **Page Filament** : `app/Filament/Pages/TreeManager.php`, extend `Lunar\Admin\Support\Pages\BasePage`, enregistrée via `LunarPanel::panel()->pages([...])` dans `AppServiceProvider::register()`.
- **Livewire state public** : `$collectionGroupId`, `$collectionsTree` (nested array), `$featureFamilies` (flat array famille → values).
- **Côté catégories** : `Lunar\Models\Collection` + `NodeTrait` kalnoy/nestedset — arbo illimitée, reparenting par `saveAsRoot()` / `appendToNode()`, repositionnement exact via `insertBeforeNode()`.
- **Côté caractéristiques** : 2 niveaux stricts `FeatureFamily` → `FeatureValue`, position entière, renumérotation manuelle en transaction lors d'un drag (y compris renumbering de la famille source quand une valeur change de parent).
- **Modales CRUD** : Filament Actions (`createCollectionAction`, `editCollectionAction`, `deleteCollectionAction`, équivalents famille / valeur). Chaque action a son `->form()` et son `->fillForm()`. Déclenchées depuis la blade via `wire:click="mountAction('xxxAction', { id: 42 })"`.
- **Tab switcher** : 3 `Action` objects dans `getHeaderActions()` (catégories / caractéristiques / les deux) avec closures `->color()` dynamiques. Méthode `switchTab()` invalide le cache `$this->cachedHeaderActions` car Filament le fige au boot. Un `ActionGroup` dropdown ("...") regroupe les actions de maintenance (Réparer l'arbre).
- **Layout** : mode `both` = inline `style="grid-template-columns: repeat(2, minmax(0, 1fr))"` (pas Tailwind JIT, cf. section 13). Responsive via `@media (max-width: 1023px)` en CSS inline.
- **Drag-n-drop** : **SortableJS 1.15.2** chargé via **CDN** (`cdn.jsdelivr.net`) directement dans la blade — pas de dépendance npm, pas d'entry Vite admin. Alpine component `treeManager` inline attache Sortable sur chaque `<ul data-sortable="...">` et appelle `$wire.moveCollection / moveFeatureValue / moveFeatureFamily` au `onEnd`. Réinitialisation post-Livewire via `Livewire.hook('morph.updated')`. Les listes `collections` et `collection-children` partagent le groupe SortableJS `{ name: 'collections-tree', pull: true, put: true }` pour le reparenting cross-level. Même pattern `features-values` côté caractéristiques.
- **Import / export JSON** : boutons Export/Import dans le `headerEnd` slot de chaque `<x-filament::section>`, contextuels par arbre. Format versionné (`version: 1`). Import = transaction + `fixTree()` final côté collections, `updateOrCreate` par handle côté features (préserve l'ID existant).
  - **UX modales** : la modale Export affiche le JSON dans un `<textarea readonly>` avec un bouton « Copier » (clipboard) et « Télécharger JSON » (download fichier). La modale Import accepte un JSON collé dans un textarea **ou** un upload fichier (les deux options sont proposées). Côté helpers partagés : `clipboardJs()` fournit la copie Alpine via `alpineClickHandler()` ; `resolveImportPayload()` priorise le fichier uploadé, puis le textarea, ou abort 422 si vide.
  - **Sélection d'export (catégories)** : chaque nœud porte une **case à cocher de sélection** (état Alpine `exportSelected`, tout coché par défaut) **indépendante** de l'activation storefront (`pko_enabled`). Le bouton « Tout cocher / Tout décocher » ne fait que (dé)sélectionner ces cases — il **ne modifie plus** `pko_enabled` (l'ancien comportement désactivait les catégories, ce qui n'était pas voulu). L'export synchronise les IDs cochés dans `collectionExportSelection` (Livewire) puis `serializeCollectionsTree()` élague l'arbre aux nœuds cochés **et leurs ancêtres**. Sélection vide = tout exporter.
  - **Formats d'entrée acceptés (catégories)** : `resolveCollectionsTreePayload()` normalise quatre formes vers une liste de nœuds `{name, img_src?, children}` — `{"tree": […]}` (export natif), `{"categories": […]}` (**export PrestaShop**, porte `img_src`), une liste de nœuds nue, ou la map plate legacy `{"Parent": ["Enfant"]}` via `flatMapToTree()`. Avant, un payload racine `categories` tombait par erreur dans le parseur de map plate et produisait un arbre corrompu.
  - **Visuels de catégorie par URL (`img_src`)** : chaque nœud peut porter un `img_src`. L'image est téléchargée dans la **médiathèque**, dossier `categories` (nom affiché « Catégories », créé au besoin via `MediaLibraryImporter::resolveFolder()`), puis **copiée** dans la collection Spatie `images` de la catégorie Lunar — c'est celle que lit le storefront (`getFirstMediaUrl('images', 'small')`) et la seule qui génère les conversions Lunar. **Déduplication** portée par `MediaLibraryImporter` (`custom_properties.source_url` + `sha1`) : ré-importer le même JSON ne re-télécharge pas et ne crée pas de doublon en médiathèque. Les téléchargements sont **différés après le commit** de la transaction d'import (une centaine d'appels HTTP dans la transaction la garderait ouverte plusieurs minutes → locks + timeout). `importCollectionsPayload()` retourne `{imported, skipped, errors}`, affiché en corps de notification. L'export est symétrique : `collectionImageSource()` ré-émet le `source_url` d'origine (ou l'URL publique locale à défaut).
  - **Mode d'import (catégories)** : un `Radio` « Ajouter à la suite » (défaut) vs « Écraser » est proposé. *Ajouter* crée les catégories importées comme **nouveaux** nœuds (IDs entrants ignorés) sans toucher aux existantes. *Écraser* purge d'abord toutes les catégories du groupe (`purgeCollectionsForCurrentGroup()` : détache les pivots FK puis efface en masse) avant d'importer. Dans les deux modes, l'import crée systématiquement de nouveaux nœuds (pas d'upsert par ID → pas d'écrasement d'un autre groupe par effet de bord).

### 7.ter.3 SEO catégories — choix de stockage

`meta_title` et `meta_description` sont stockés comme **Lunar Attributes translatables** dans `attribute_data` (type `Lunar\FieldTypes\TranslatedText`, section `seo`), **pas** en colonnes plates sur `lunar_collections`. Ils apparaissent donc aussi dans l'onglet « Attributs » de Lunar Admin standard, restent multi-langue, et n'imposent aucune migration structurelle sur une table Lunar.

Seeder : migration `2026_04_11_140000_add_pko_seo_collection_attributes.php` — crée (si absent) un AttributeGroup `collection_seo` et les 2 attributes via `Attribute::updateOrCreate` keyés sur `(attribute_type=collection, handle)`. Idempotent, rollback supprime les 2 handles.

### 7.ter.4 SortableJS via CDN — pourquoi pas npm

- Pas d'entry Vite admin dédié dans le projet (`resources/js/app.js` cible le front Blade)
- `FilamentAsset::register([Js::make(...)])` exigerait un build Vite pour résoudre l'URL, complexité inutile pour 1 page
- SortableJS n'a pas de dépendances, 45 Ko min, version épinglée en dur dans l'URL CDN → reproductible
- Chargement scopé à la page (balise inline dans `tree-manager.blade.php`), pas d'impact sur le reste de l'admin

Bascule npm envisageable si une 2ᵉ page admin a besoin de la même lib — créer alors `resources/js/admin.js` + `FilamentAsset::register()` dans `AppServiceProvider::boot()`.

### 7.ter.5 Toggle activation/désactivation catégorie

**Colonne** : `pko_enabled BOOLEAN NOT NULL DEFAULT 1` sur `lunar_collections` (migration `2026_06_08_120000_add_pko_enabled_to_lunar_collections.php`, index ajouté). Ne pas modifier les migrations Lunar publiées.

**Comportement** :
- Désactiver → (a) collection masquée du menu front, (b) sa page `/collections/{slug}` renvoie 404, (c) cascade nestedset : toutes les sous-catégories (`_lft > parent._lft AND _rgt < parent._rgt`) passent aussi à `pko_enabled=false`, (d) les produits dont c'est la **seule** collection visible sont masqués.
- Réactiver → **uniquement le nœud lui-même** (`pko_enabled=true`). Les enfants gardent leur état.

**Back-office — TreeManager** :
- `collectionsTree()` expose `pko_enabled` dans chaque nœud.
- Bouton toggle (œil / œil barré) dans `tree-node__actions` → `wire:click="toggleCollectionEnabled($id)"`.
- Nœuds désactivés rendus en `opacity-50` + icône dossier en rouge + badge « désactivée ».
- `toggleCollectionEnabled()` : transaction SQL + cascade nestedset sur disable + `Cache::forget('pko.storefront.nav.roots.v3')` + `unset($this->collectionsTree)`.

**Back-office — fiche Collection** :
- Extension `CollectionEnabledExtension` (enregistrée sur `CollectionResource` dans `AppServiceProvider`) injecte un `Toggle('pko_enabled')` dans une section « Visibilité » collapsée quand activée.

**Hors scope** :
- Sélecteur `CollectionGroup` (la page utilise le premier groupe par ID — le projet n'en a qu'un en pratique)
- Image ou SEO sur `FeatureFamily` / `FeatureValue` (décision : caractéristiques restent purement fonctionnelles)
- Authorization fine : réutilise la policy Shield `page_TreeManager` générée automatiquement, rattachée au rôle `admin`. À régénérer via `make artisan CMD='shield:generate --panel=lunar'` après déploiement.

### 7.ter.6 Architecture performance (500+ nœuds)

Le TreeManager gère 500+ nœuds (catégories + familles + valeurs). Six décisions architecturales garantissent la fluidité :

**Livewire `#[Computed]` au lieu de `public`** — `collectionsTree` et `featureFamilies` sont des propriétés `#[Computed]`, pas `public`. Elles sont exclues du snapshot Livewire (sérialisé à chaque requête), réduisant le payload de ~80 KB à ~2 KB.

**`skipRender()` sur drag-drop** — Les 3 méthodes de déplacement (`moveCollection`, `moveFeatureFamily`, `moveFeatureValue`) appellent `$this->skipRender()` car SortableJS a déjà mis à jour le DOM côté client. Pas de re-rendu serveur nécessaire.

**CRUD dynamique via `unset()` + morph Livewire** — Les actions CRUD invalident le cache computed (`unset($this->collectionsTree)`) ce qui déclenche une requête fraîche et un morph DOM Livewire. Pas de rechargement de page.

**`withCount('products')` sur les collections** — Élimine les requêtes N+1 (504 COUNT individuels remplacés par une seule requête avec sous-SELECT COUNT).

**Recherche Alpine-only** — Le filtrage de recherche tourne entièrement côté client via Alpine.js (`x-on:input.debounce`), sans jamais déclencher de requête Livewire. Matching récursif : seuls les nœuds dont le label contient la query + leurs ancêtres structurels sont affichés. Le texte correspondant est surligné avec des éléments `<mark>`.

**SortableJS lazy init via WeakMap** — Les instances Sortable sont trackées dans un `WeakMap`. Après un morph Livewire, seuls les NOUVEAUX éléments `[data-sortable]` (absents du map) sont initialisés. Debounce via `requestAnimationFrame` pour battre les événements `morph.updated` multiples.

**Tous les nœuds dépliés par défaut** — Le CSS affiche les nœuds dépliés (`.tree-children` visible). Le repliage se fait par toggle de la classe `.tree-collapsed`. Pas de vérification `offsetParent`.

### 7.ter.7 Contrôles bulk d'en-tête + densité

**Layout d'en-tête** : le titre + stats de chaque colonne sont rendus **au-dessus** du cadre blanc (`<x-filament::section>`), et les boutons occupent une **ligne pleine** (`flex flex-wrap justify-end`) en tête de la section — évite le chevauchement titre/boutons quand la barre d'actions est chargée.

Trois contrôles ajoutés en tête de chaque section pour améliorer la vue d'ensemble :

**Toggle « Tout réduire / Tout déplier »** (les deux sections) — bouton unique qui bascule son état au clic (label + icône changent via Alpine `x-text` / `x-show`). Fonction Alpine `toggleCollapseAll(selector)` : ajoute/retire la classe `.tree-collapsed` sur tous les `<li>` ayant un `.tree-children` direct de la liste ciblée. État suivi dans `collapsed['.tree-list--collections' | '.tree-list--families']` (persiste à travers les morphs Livewire). 100 % client, aucune requête serveur.

**Toggle « Tout cocher / Tout décocher »** (catégories uniquement) — active/désactive `pko_enabled` sur **toutes** les collections du groupe en une requête. Méthode `setAllCollectionsEnabled(bool)` : `UPDATE` de masse + `Cache::forget(NAV_CACHE_KEY)` + `unset($this->collectionsTree)`. État initial du bouton dérivé de `allCollectionsEnabled()` (vrai si aucune collection `pko_enabled=false`), injecté dans Alpine via `allEnabled: @js($this->allCollectionsEnabled())`. Pas de toggle côté caractéristiques (pas d'état d'activation sur `FeatureFamily` / `FeatureValue`).

**Densité compacte** — padding des `.tree-node` réduit (`0.1875rem 0.5rem`), label `0.8125rem`, badges `0.65rem`, indentation `.tree-children` resserrée — pour afficher davantage de nœuds en hauteur et améliorer la vue d'ensemble.

---

