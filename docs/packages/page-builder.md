# pko/lunar-page-builder — mini builder JSON

Package `packages/pko/page-builder/` (namespace `Pko\PageBuilder\`), `PageBuilderServiceProvider`. Mini-builder JSON pour les pages CMS (`Pko\StorefrontCms\Models\Page`) et les articles (`Post`), pensé pour édition par des non-devs **et** pour la génération IA (JSON schema documenté).

### Schéma JSON canonique

```json
{
  "sections": [
    {
      "id": "sec_xxxx",
      "layout": "1col" | "2col" | "3col",
      "padding": {"t":0,"r":0,"b":0,"l":0},
      "margin":  {"t":0,"b":0},
      "background_color": "#rrggbb" | null,
      "text_color": "#rrggbb" | null,
      "columns": [
        {
          "blocks": [
            { "id": "blk_...", "type": "text", "html": "<p>…</p>" },
            { "id": "blk_...", "type": "image", "media_id": 42, "url": null, "alt": "…" },
            { "id": "blk_...", "type": "code", "language": "php", "content": "…" }
          ]
        }
      ]
    }
  ]
}
```

JSON Schema draft-07 officiel : `packages/pko/page-builder/resources/schema/content.schema.json`. Référence cible pour les prompts IA (« Génère-moi une page blog conforme à ce schema »).

### Stockage

Colonne **`content` JSON nullable** ajoutée sur `pko_pages` et `pko_posts` (migration `2026_04_20_120001`). La colonne `body` (longText HTML legacy) reste pour le fallback : le renderer l'affiche en brut si `content` est null. Un cast `'content' => 'array'` sur les modèles `Page` et `Post`.

### API publique — `PageBuilderManager`

```php
PageBuilderManager::normalize(?array $content): array                 // canonicalise, jamais ne throw
PageBuilderManager::newSection(string $layout = '1col'): array        // section vide normalisée
PageBuilderManager::newBlock(string $type): ?array                    // bloc vide, null si type inconnu
PageBuilderManager::allowedLayouts(): array                           // ['1col','2col','3col']
PageBuilderManager::columnsForLayout(string $layout): int             // 1|2|3
```

Toutes les mutations UI et imports IA passent par `normalize()` : defaults appliqués, valeurs hors bornes clampées (padding/margin 0-400), couleurs forcées `#rrggbb`, types de blocs inconnus droppés.

### Éditeur admin — Livewire `pko-page-builder`

Composant Livewire (`Pko\PageBuilder\Livewire\PageBuilder`) monté dans une vue custom substituant `EditPage` et `EditPost` (`protected static string $view = 'page-builder::filament.edit-with-builder'` + `getMaxContentWidth(): MaxWidth::Full` pour gagner la pleine largeur). Props : `modelClass` + `recordId`. Le RichEditor `body` legacy a été retiré des forms Page + Post (colonne DB conservée en fallback renderer seulement).

**Layout** : palette sticky à gauche (200px) + éditeur pleine largeur à droite. Aperçu déporté dans un **slide-over** à droite déclenché par le bouton "Aperçu" (Alpine `x-data="{ previewOpen: false }"` + `x-teleport="body"` pour sortir du conteneur scrollable Filament, ESC ou clic backdrop pour fermer).

**Interactions** :

- **Palette drag&drop** via 3 directives Alpine (`x-pb-palette`, `x-pb-drop`, `x-pb-sortable`) configurées sur SortableJS avec un même `group: 'pko-page-builder'`.
  - `x-pb-palette` : `pull: 'clone', put: false, sort: false` sur la palette
  - `x-pb-drop` : `put: true, sort: false` avec `data-drop-type="sections|blocks"` + (pour blocks) `data-section-index`, `data-column-index`. Sur `onAdd`, lit `evt.item.dataset.paletteType`, remove le clone DOM immédiatement, puis appelle `insertSection(index, type)` ou `insertBlock(s, c, b, type)` sur le Livewire component
  - `x-pb-sortable` : reorder via poignée dédiée (handle `.pko-section-handle`), appelle `reorderSections(ids)`
- **Sections** : layout switch (1/2/3col), add/remove, styling inline (padding/margin en px, `<input type="color">` natif pour background/text)
- **Bloc texte** : Filament Action `editText` avec form `TiptapEditor::make('html')` (réutilise le tiptap déjà configuré), ouvre une modal 5xl
- **Bloc image** : `openImagePicker($blockId)` dispatche `open-media-picker-modal` (événement partagé avec l'éditeur produit) avec `statePath: 'pko-page-builder-image'` pour disambiguer. Listener `#[On('media-picked')] onMediaPicked(string $statePath, array $ids, array $medias)` — **signature avec params nommés individuels** (Livewire 3 binde chaque key du dispatch à un param, pas un `array $payload` global)
- **Bloc code** : select langage (allowlist 10 langues) + textarea monospace, binding direct via `wire:change`

### Preview

Slide-over à droite (déclenché par bouton) rend `<x-page-builder::render :content="$this->tree" />` — **exactement le même Blade component** que la vue publique. Re-render automatique à chaque interaction Livewire. Pas de templating séparé admin/front → zéro drift. Volontairement **pas en permanence** à l'écran pour laisser l'éditeur respirer en pleine largeur.

### Rendu public — `<x-page-builder::render>`

```blade
<x-page-builder::render :content="$page->content" fallback="{{ $page->body }}" />
```

- `content` nullable : si array normalisable → rendu block-builder, sinon fallback HTML brut
- Blocs image : lookup `Spatie\MediaLibrary\MediaCollections\Models\Media::find(media_id)`, fallback sur `url` brute si fourni par l'import
- Blocs code : `<pre class="language-{X}">` prêt pour Prism.js en post-hook
- Layout responsive : `grid-cols-1 md:grid-cols-{N}` sur le wrapper colonnes

### Permission Shield

`manage_cms_pages` (guard `staff`) — créée par `CmsPermissionsSeeder`, assignée à `super_admin`. Attachée au Livewire pour futurs gatings spécifiques (ex: séparer page vs post).

### Rejets v1 documentés

- **Pas de templates / sections réutilisables** — chaque page repart d'un builder vide (pas de bibliothèque de sections pré-faites). Possible phase 2.
- **Pas de largeur de colonne asymétrique** (1/3-2/3, 2/3-1/3) — que 1col / 2col égal / 3col égal.
- **Pas de styling par-bloc** — padding/margin/couleurs uniquement au niveau section.
- **Pas de révisions / drafts séparés** — le record stocke le contenu courant uniquement. Le `status` existant (`draft` / `published`) reste honoré par le storefront.
- **Pas de multi-langue** — Lunar a son i18n natif pour les produits, le CMS n'est pas branché dessus v1.
- **Pas d'inline WYSIWYG** — toute édition se fait dans le panneau gauche + modal TipTap, pas de clic-direct-sur-le-rendu à la GrapesJS (volontaire : gain de simplicité et de robustesse).
- **Pas de cross-column block move** — un bloc peut être supprimé puis recréé dans une autre colonne, mais pas déplacé par drag. Possible v2.

### Intégration IA (à venir)

Le JSON Schema canonical peut être embarqué dans un prompt :

> Tu es un rédacteur. Retourne **uniquement** un JSON conforme à ce schema : `<contenu content.schema.json>`. Sujet : {…}.

La sortie peut être persistée telle quelle via `$page->content = PageBuilderManager::normalize($jsonIaDecoded)`, sans UI. Utile pour auto-générer des landing pages ou articles depuis un brief utilisateur.

