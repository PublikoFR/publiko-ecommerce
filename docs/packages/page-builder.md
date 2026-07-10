# pko/lunar-page-builder — mini builder JSON

Package `packages/pko/page-builder/` (namespace `Pko\PageBuilder\`), `PageBuilderServiceProvider`. Mini-builder JSON pour les pages CMS (`Pko\StorefrontCms\Models\Page`) et les articles (`Post`), pensé pour édition par des non-devs **et** pour la génération IA (JSON schema documenté).

### Schéma JSON canonique

```json
{
  "heading": "Titre H1 affiché en haut de page (on-page)",
  "sections": [
    {
      "id": "sec_xxxx",
      "layout": "1col" … "6col",
      "padding": {"t":0,"r":0,"b":0,"l":0},
      "margin":  {"t":0,"b":0},
      "background_color": "#rrggbb" | null,
      "text_color": "#rrggbb" | null,
      "columns": [
        {
          "blocks": [
            { "id": "blk_...", "type": "text", "html": "<p>…</p>" },
            { "id": "blk_...", "type": "image", "media_id": 42, "url": null, "alt": "…" },
            { "id": "blk_...", "type": "code", "language": "php", "content": "…" },
            { "id": "blk_...", "type": "quote", "text": "…", "cite": "Auteur" },
            { "id": "blk_...", "type": "button", "label": "Voir", "url": "/contact", "variant": "primary|accent|secondary" },
            { "id": "blk_...", "type": "separator", "variant": "line|space" },
            { "id": "blk_...", "type": "callout", "variant": "info|warning|danger", "text": "…" }
          ]
        }
      ]
    }
  ]
}
```

- **`heading`** (top-level, optionnel) : titre **H1 on-page**. Peut différer du nom du contenu en base (`Post::$title`) pour le SEO — ex. record « Contact », H1 « Envoyez-nous un message ». Non supprimable côté éditeur (champ dédié, pas un bloc du canvas). Texte brut (tags retirés), borné à 250 caractères.
- **Blocs** : `text`, `image`, `code`, plus `quote` (citation + source), `button` (label + url sûre + variante DS), `separator` (ligne / espace) et `callout` (encart `info` bleu / `warning` orange / `danger` rouge, picto + bordure gauche). Les URL de bouton sont restreintes à `http(s)://`, lien interne `/…`, ancre `#…`, `mailto:`/`tel:` (le reste, dont `javascript:`, est neutralisé). Les tuiles callout de la palette encodent la variante dans le `data-palette-type` (`callout-info|warning|danger`) → `newBlock()` la mappe.
- **Colonnes** : les sections vont de **1 à 6 colonnes** (`1col`…`6col`). Front responsive (ex. `6col` = `grid-cols-2 md:grid-cols-3 lg:grid-cols-6`).

JSON Schema draft-07 officiel : `packages/pko/page-builder/resources/schema/content.schema.json`. Référence cible pour les prompts IA (« Génère-moi une page blog conforme à ce schema »).

### Stockage

Colonne **`content` JSON nullable** ajoutée sur `pko_pages` et `pko_posts` (migration `2026_04_20_120001`). La colonne `body` (longText HTML legacy) reste pour le fallback : le renderer l'affiche en brut si `content` est null. Un cast `'content' => 'array'` sur les modèles `Page` et `Post`. Le titre H1 on-page (`content.heading`) est stocké **dans ce même JSON** — pas de colonne dédiée, donc pas de migration.

### API publique — `PageBuilderManager`

```php
PageBuilderManager::normalize(?array $content): array                 // -> ['heading' => string, 'sections' => [...]], jamais ne throw
PageBuilderManager::normalizeHeading(mixed $value): string            // strip_tags + trim + borne 250
PageBuilderManager::newSection(string $layout = '1col'): array        // section vide normalisée
PageBuilderManager::newBlock(string $type): ?array                    // bloc vide, null si type inconnu
PageBuilderManager::allowedLayouts(): array                           // ['1col','2col','3col']
PageBuilderManager::columnsForLayout(string $layout): int             // 1|2|3
```

Toutes les mutations UI et imports IA passent par `normalize()` : defaults appliqués, valeurs hors bornes clampées (padding/margin 0-400), couleurs forcées `#rrggbb`, variantes bouton/séparateur ramenées à leur allowlist, URL de bouton neutralisées si non sûres, types de blocs inconnus droppés. **Note** : `normalize()` retourne désormais `['heading' => …, 'sections' => …]` (clé `heading` ajoutée) — les appelants qui lisaient `['sections']` restent compatibles.

### Éditeur admin — Livewire `pko-page-builder`

Composant Livewire (`Pko\PageBuilder\Livewire\PageBuilder`) monté en pleine largeur (`getMaxContentWidth(): MaxWidth::Full`). Depuis le redesign « Éditeur de contenu Weklo » (maquette Design System), le composant est **unifié** et pilote deux modes via la prop `bool $withMeta` :

| Prop | Rôle |
|---|---|
| `modelClass` | FQCN du modèle (`Post`, `BrandPage`) |
| `recordId` | id du record |
| `withMeta` | `true` (édition d'un **Post**) : onglet « Page » (métadonnées), H1 on-page, topbar + barre Enregistrer/Publier/Supprimer. `false` (défaut, **pages de marque**) : blocs seuls |
| `indexUrl` | URL de retour après suppression (mode `withMeta`) |

**Mode `withMeta` = unification totale** : titre / slug / type de contenu / extrait / statut / date / SEO **et** couverture sont édités *dans* le composant Livewire (plus de form Filament au-dessus). `EditPost` ne rend plus que le composant (`edit-with-builder.blade.php`), son header Filament est masqué (l'éditeur fournit sa propre topbar). Un seul `save()` (garde le statut) + `publish()` (force `published` + `published_at`). Validation slug **unique par `post_type_id`** dans `save()`. La couverture réutilise le media picker (`statePath: 'pko-page-builder-cover'`, sync via `HasMediaAttachments::syncMediaAttachments($ids, 'cover')`).

**Layout (maquette)** : topbar (fil d'Ariane + titre + badge statut + Aperçu/Supprimer) · body en 2 volets [canvas central | **panneau droit à onglets Page / Blocs**, 340px] · **barre d'action sticky en bas** qui démarre au bord de la sidebar dashboard (gauche) et passe par-dessus le panneau blocs (droite). Le H1 est un **bloc épinglé non supprimable** en tête du canvas (`wire:model.blur="heading"`), avec rappel qu'il peut différer du nom en base.

**Design System dans le panel Filament** : styles scopés `.wk-editor` dans `resources/css/filament/admin/modules/page-editor.css` (importé par `theme.css`), même pattern que `.wk-dash` — les tokens DS (forest/lime/surfaces/radius/shadow/fonts) sont redéfinis localement pour ne pas polluer le reste de Filament. En mode plein, `.fi-main:has(.wk-editor--full)` casse le padding + masque `.fi-header`.

**Interactions** :

- **Palette drag&drop** via 3 directives Alpine (`x-pb-palette`, `x-pb-drop`, `x-pb-sortable`) sur SortableJS, même `group: 'pko-page-builder'`. La palette (onglet Blocs) expose sections 1/2/3col + blocs texte/image/code/citation/bouton/séparateur (`data-palette-type`).
  - `x-pb-drop` : `data-drop-type="sections|blocks"` (+ `data-section-index`, `data-column-index`), `onAdd` → `insertSection` / `insertBlock`
  - `x-pb-sortable` : reorder sections via poignée `.wk-handle`, appelle `reorderSections(ids)`
- **Sections** : layout switch (1/2/3col), add/remove, styling inline (padding/margin px, `<input type="color">`)
- **Blocs** : toolbar flottante par bloc (dupliquer `duplicateBlock` + supprimer)
  - **texte** : Filament Action `editText` + `TiptapEditor::make('html')` (modal 5xl)
  - **image** : `openImagePicker` + media picker (`statePath: 'pko-page-builder-image'`, listener `#[On('media-picked')]` à params nommés individuels)
  - **code** : select langage (allowlist 10) + textarea monospace, `wire:change`
  - **citation / bouton / séparateur** : édition inline `wire:change` (`updateQuoteBlock` / `updateButtonBlock` / `updateSeparatorBlock`, re-normalisés pour ré-appliquer les allowlists)

### Preview

Slide-over à droite rend `<x-page-builder::render :content="$this->tree" :with-heading="true" />` — **même Blade component** que le front (avec le H1 on-page). Re-render auto à chaque interaction. Pas de drift admin/front.

### Rendu public — `<x-page-builder::render>`

```blade
<x-page-builder::render :content="$page->content" fallback="{{ $page->body }}" />
```

- `content` nullable : si array normalisable → rendu block-builder, sinon fallback HTML brut
- **`with-heading`** (bool, défaut `false`) : rend `content.heading` en `<h1>` en tête. Laissé à `false` côté front (les templates gèrent déjà leur `<header>`), passé à `true` pour l'aperçu admin. Côté `posts/show.blade.php`, le H1 visible est sourcé sur `data_get($post->content, 'heading') ?: $post->title` → le nom en base sert nav/listing, le heading sert le H1 on-page (distinction SEO)
- Blocs image : lookup `Spatie\MediaLibrary\MediaCollections\Models\Media::find(media_id)`, fallback sur `url` brute si fourni par l'import
- Blocs code : `<pre class="language-{X}">` prêt pour Prism.js en post-hook
- Blocs `quote` / `button` / `separator` : partials `block-quote|block-button|block-separator.blade.php` (bouton stylé DS `primary|accent|secondary`)
- Layout responsive : `grid-cols-1 md:grid-cols-{N}` sur le wrapper colonnes

### Permission Shield

`manage_cms_pages` (guard `staff`) — créée par `CmsPermissionsSeeder`, assignée à `super_admin`. Attachée au Livewire pour futurs gatings spécifiques (ex: séparer page vs post).

### Rejets v1 documentés

- **Pas de templates / sections réutilisables** — chaque page repart d'un builder vide (pas de bibliothèque de sections pré-faites). Possible phase 2.
- **Pas de largeur de colonne asymétrique** (1/3-2/3, 2/3-1/3) — que 1col / 2col égal / 3col égal.
- **Pas de styling par-bloc** — padding/margin/couleurs uniquement au niveau section (bouton/séparateur ont juste une variante prédéfinie).
- **Pas de révisions / drafts séparés** — le record stocke le contenu courant uniquement. Le `status` existant (`draft` / `published`) reste honoré par le storefront.
- **Pas de multi-langue** — Lunar a son i18n natif pour les produits, le CMS n'est pas branché dessus v1.
- **Pas d'inline WYSIWYG** — toute édition se fait dans le panneau gauche + modal TipTap, pas de clic-direct-sur-le-rendu à la GrapesJS (volontaire : gain de simplicité et de robustesse).
- **Pas de cross-column block move** — un bloc peut être supprimé puis recréé dans une autre colonne, mais pas déplacé par drag. Possible v2.

### Intégration IA (à venir)

Le JSON Schema canonical peut être embarqué dans un prompt :

> Tu es un rédacteur. Retourne **uniquement** un JSON conforme à ce schema : `<contenu content.schema.json>`. Sujet : {…}.

La sortie peut être persistée telle quelle via `$page->content = PageBuilderManager::normalize($jsonIaDecoded)`, sans UI. Utile pour auto-générer des landing pages ou articles depuis un brief utilisateur.

