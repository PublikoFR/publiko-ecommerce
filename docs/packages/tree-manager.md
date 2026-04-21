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

### 7.ter.3 SEO catégories — choix de stockage

`meta_title` et `meta_description` sont stockés comme **Lunar Attributes translatables** dans `attribute_data` (type `Lunar\FieldTypes\TranslatedText`, section `seo`), **pas** en colonnes plates sur `lunar_collections`. Ils apparaissent donc aussi dans l'onglet « Attributs » de Lunar Admin standard, restent multi-langue, et n'imposent aucune migration structurelle sur une table Lunar.

Seeder : migration `2026_04_11_140000_add_pko_seo_collection_attributes.php` — crée (si absent) un AttributeGroup `collection_seo` et les 2 attributes via `Attribute::updateOrCreate` keyés sur `(attribute_type=collection, handle)`. Idempotent, rollback supprime les 2 handles.

### 7.ter.4 SortableJS via CDN — pourquoi pas npm

- Pas d'entry Vite admin dédié dans le projet (`resources/js/app.js` cible le front Blade)
- `FilamentAsset::register([Js::make(...)])` exigerait un build Vite pour résoudre l'URL, complexité inutile pour 1 page
- SortableJS n'a pas de dépendances, 45 Ko min, version épinglée en dur dans l'URL CDN → reproductible
- Chargement scopé à la page (balise inline dans `tree-manager.blade.php`), pas d'impact sur le reste de l'admin

Bascule npm envisageable si une 2ᵉ page admin a besoin de la même lib — créer alors `resources/js/admin.js` + `FilamentAsset::register()` dans `AppServiceProvider::boot()`.

### 7.ter.5 Hors scope

- Sélecteur `CollectionGroup` (la page utilise le premier groupe par ID — le projet nen a quun en pratique)
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

---

