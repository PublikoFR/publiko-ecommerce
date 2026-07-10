<?php

declare(strict_types=1);

namespace Pko\PageBuilder\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use FilamentTiptapEditor\Enums\TiptapOutput;
use FilamentTiptapEditor\TiptapEditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Pko\PageBuilder\Services\PageBuilderManager;
use Pko\StorefrontCms\Models\PostType;

/**
 * Editor Livewire autonome pour un contenu page-builder. Se monte dans une
 * Filament Page custom avec en props :
 *   - $modelClass : FQCN du modèle (Pko\StorefrontCms\Models\Post|BrandPage)
 *   - $recordId   : id du record à éditer
 *   - $withMeta   : true pour l'éditeur unifié (Post) — active l'onglet « Page »
 *                   (titre/slug/statut/SEO/couverture), le titre H1 on-page, la
 *                   barre d'action Enregistrer/Publier et la suppression. false
 *                   (défaut) pour un usage « blocs seuls » (pages de marque).
 *
 * Le contenu est 100% dans $this->sections + $this->heading (shape normalisée
 * par PageBuilderManager::normalize). Les mutations passent toutes par
 * PageBuilderManager::newSection / newBlock pour garantir les defaults.
 *
 * Interactions :
 *   - Text  : Filament Action "editText" avec un form TiptapEditor
 *   - Image : dispatch open-media-picker-modal (événement existant du projet),
 *             listener #[On('media-picked')] route la sélection vers le bloc courant
 *   - Cover : même canal media picker (statePath 'pko-page-builder-cover')
 *   - Code / Quote / Button / Separator : édition inline via wire:change
 */
class PageBuilder extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** @var class-string<Model> */
    public string $modelClass;

    public int $recordId;

    /** Active l'onglet métadonnées + le H1 on-page + la barre publier/supprimer. */
    public bool $withMeta = false;

    /** URL de retour après suppression (liste Filament). */
    public ?string $indexUrl = null;

    /** @var array<int, array<string, mixed>> */
    public array $sections = [];

    /** Titre H1 on-page (peut différer du nom en base, SEO). */
    public string $heading = '';

    public bool $isDirty = false;

    // ---------- Métadonnées (mode $withMeta uniquement) ----------

    public ?int $postTypeId = null;

    public string $title = '';

    public string $slug = '';

    public ?string $excerpt = null;

    public string $status = 'draft';

    public ?string $publishedAt = null;

    public ?string $seoTitle = null;

    public ?string $seoDescription = null;

    public ?int $coverMediaId = null;

    public ?string $coverUrl = null;

    /** @var array<int, array{id:int,label:string,url_segment:string}> */
    public array $postTypeOptions = [];

    /** Identifiant du bloc image en cours de sélection (pour le media picker). */
    public ?string $pickingImageBlockId = null;

    /** Identifiant du bloc galerie en cours de sélection (media picker multiple). */
    public ?string $pickingGalleryBlockId = null;

    /** @param  class-string<Model>  $modelClass */
    public function mount(string $modelClass, int $recordId, bool $withMeta = false, ?string $indexUrl = null): void
    {
        $this->modelClass = $modelClass;
        $this->recordId = $recordId;
        $this->withMeta = $withMeta;
        $this->indexUrl = $indexUrl;

        $record = $this->loadRecord();
        $tree = PageBuilderManager::normalize($record->content ?? null);
        $this->sections = $tree['sections'];

        if ($withMeta) {
            $this->hydrateMeta($record);
            // H1 par défaut = nom du contenu en base, surchargeable ensuite.
            $this->heading = $tree['heading'] !== '' ? $tree['heading'] : (string) ($record->title ?? '');
        } else {
            $this->heading = $tree['heading'];
        }
    }

    private function hydrateMeta(Model $record): void
    {
        $this->postTypeId = $record->post_type_id !== null ? (int) $record->post_type_id : null;
        $this->title = (string) ($record->title ?? '');
        $this->slug = (string) ($record->slug ?? '');
        $this->excerpt = $record->excerpt;
        $this->status = (string) ($record->status ?? 'draft');
        $this->publishedAt = $record->published_at
            ? Carbon::parse($record->published_at)->format('Y-m-d\TH:i')
            : null;
        $this->seoTitle = $record->seo_title;
        $this->seoDescription = $record->seo_description;
        $this->coverUrl = method_exists($record, 'firstMediaUrl') ? $record->firstMediaUrl('cover') : null;
        $this->coverMediaId = $record->firstMedia('cover')?->getKey();

        $this->postTypeOptions = PostType::query()
            ->orderBy('sort_order')
            ->get(['id', 'label', 'url_segment'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'label' => (string) $t->label, 'url_segment' => (string) $t->url_segment])
            ->all();
    }

    public function render(): View
    {
        return view('page-builder::livewire.page-builder');
    }

    /** Marque l'éditeur dirty dès qu'une prop bindée (heading/méta) change. */
    public function updated(string $property): void
    {
        if ($property === 'heading' || str_starts_with($property, 'title')
            || in_array($property, ['slug', 'excerpt', 'status', 'publishedAt', 'seoTitle', 'seoDescription', 'postTypeId'], true)) {
            $this->isDirty = true;
        }

        // Auto-slug depuis le titre tant que le slug est vide.
        if ($property === 'title' && $this->withMeta && trim($this->slug) === '') {
            $this->slug = Str::slug($this->title);
        }
    }

    // ---------- State getters

    /**
     * @return array{heading: string, sections: array<int, array<string, mixed>>}
     */
    public function getTreeProperty(): array
    {
        return ['heading' => $this->heading, 'sections' => $this->sections];
    }

    /** Segment d'URL du type de contenu sélectionné (pour le hint slug). */
    public function getUrlSegmentProperty(): string
    {
        foreach ($this->postTypeOptions as $opt) {
            if ($opt['id'] === $this->postTypeId) {
                return $opt['url_segment'];
            }
        }

        return '';
    }

    /** Nombre total de blocs (indicateur barre d'action). */
    public function getBlockCountProperty(): int
    {
        $count = 0;
        foreach ($this->sections as $section) {
            foreach ($section['columns'] as $column) {
                $count += count($column['blocks']);
            }
        }

        return $count;
    }

    /** @return array<string, mixed>|null */
    private function findBlock(string $blockId): ?array
    {
        foreach ($this->sections as $section) {
            foreach ($section['columns'] as $column) {
                foreach ($column['blocks'] as $block) {
                    if (($block['id'] ?? null) === $blockId) {
                        return $block;
                    }
                }
            }
        }

        return null;
    }

    // ---------- Section mutations

    public function addSection(string $layout = PageBuilderManager::LAYOUT_1COL): void
    {
        $this->sections[] = PageBuilderManager::newSection($layout);
        $this->isDirty = true;
    }

    /**
     * Insert une section à une position donnée (drag&drop depuis la palette).
     * $paletteType = 'section-1col' … 'section-6col'
     */
    public function insertSection(int $index, string $paletteType): void
    {
        $index = max(0, min(count($this->sections), $index));
        array_splice($this->sections, $index, 0, [PageBuilderManager::newSection($this->layoutForPaletteType($paletteType))]);
        $this->isDirty = true;
    }

    /**
     * Dépôt sur la zone « déposer une section » (en bas) : ajoute toujours la
     * nouvelle section à la fin. Si $paletteType est un type de section
     * (`section-Ncol`), on crée une section vide de ce layout. Sinon c'est un
     * bloc glissé directement → on crée une section 1col et on y place le bloc.
     */
    public function dropSection(string $paletteType): void
    {
        if (str_starts_with($paletteType, 'section-')) {
            $this->sections[] = PageBuilderManager::newSection($this->layoutForPaletteType($paletteType));
            $this->isDirty = true;

            return;
        }

        $section = PageBuilderManager::newSection(PageBuilderManager::LAYOUT_1COL);
        $block = PageBuilderManager::newBlock($paletteType);
        if ($block !== null) {
            $section['columns'][0]['blocks'][] = $block;
        }
        $this->sections[] = $section;
        $this->isDirty = true;
    }

    private function layoutForPaletteType(string $paletteType): string
    {
        return match ($paletteType) {
            'section-2col' => PageBuilderManager::LAYOUT_2COL,
            'section-3col' => PageBuilderManager::LAYOUT_3COL,
            'section-4col' => PageBuilderManager::LAYOUT_4COL,
            'section-5col' => PageBuilderManager::LAYOUT_5COL,
            'section-6col' => PageBuilderManager::LAYOUT_6COL,
            default => PageBuilderManager::LAYOUT_1COL,
        };
    }

    public function removeSection(int $index): void
    {
        if (! isset($this->sections[$index])) {
            return;
        }
        unset($this->sections[$index]);
        $this->sections = array_values($this->sections);
        $this->isDirty = true;
    }

    public function setSectionLayout(int $index, string $layout): void
    {
        if (! isset($this->sections[$index])) {
            return;
        }
        // On re-normalise la section entière pour conserver/compléter les colonnes.
        $section = $this->sections[$index];
        $section['layout'] = $layout;
        $this->sections[$index] = PageBuilderManager::normalize(['sections' => [$section]])['sections'][0];
        $this->isDirty = true;
    }

    public function updateSectionPadding(int $index, string $side, int $value): void
    {
        if (! in_array($side, ['t', 'r', 'b', 'l'], true) || ! isset($this->sections[$index])) {
            return;
        }
        $this->sections[$index]['padding'][$side] = max(0, min(400, $value));
        $this->isDirty = true;
    }

    public function updateSectionMargin(int $index, string $side, int $value): void
    {
        if (! in_array($side, ['t', 'b'], true) || ! isset($this->sections[$index])) {
            return;
        }
        $this->sections[$index]['margin'][$side] = max(0, min(400, $value));
        $this->isDirty = true;
    }

    public function updateSectionColor(int $index, string $key, ?string $color): void
    {
        if (! in_array($key, ['background_color', 'text_color'], true) || ! isset($this->sections[$index])) {
            return;
        }
        $this->sections[$index][$key] = ($color === '' || $color === null)
            ? null
            : (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : null);
        $this->isDirty = true;
    }

    /** @param  array<int, string>  $ids */
    public function reorderSections(array $ids): void
    {
        $by = [];
        foreach ($this->sections as $s) {
            $by[$s['id']] = $s;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($by[$id])) {
                $out[] = $by[$id];
            }
        }
        if (count($out) === count($this->sections)) {
            $this->sections = $out;
            $this->isDirty = true;
        }
    }

    // ---------- Block mutations

    public function addBlock(int $sectionIndex, int $columnIndex, string $type): void
    {
        if (! isset($this->sections[$sectionIndex]['columns'][$columnIndex])) {
            return;
        }
        $block = PageBuilderManager::newBlock($type);
        if ($block === null) {
            return;
        }
        $this->sections[$sectionIndex]['columns'][$columnIndex]['blocks'][] = $block;
        $this->isDirty = true;
    }

    /**
     * Insert un bloc à une position donnée d'une colonne (drag&drop).
     * $paletteType = 'text' | 'image' | 'code' | 'quote' | 'button' | 'separator'
     */
    public function insertBlock(int $sectionIndex, int $columnIndex, int $blockIndex, string $paletteType): void
    {
        if (! isset($this->sections[$sectionIndex]['columns'][$columnIndex])) {
            return;
        }
        $block = PageBuilderManager::newBlock($paletteType);
        if ($block === null) {
            return;
        }
        $blocks = $this->sections[$sectionIndex]['columns'][$columnIndex]['blocks'];
        $blockIndex = max(0, min(count($blocks), $blockIndex));
        array_splice($blocks, $blockIndex, 0, [$block]);
        $this->sections[$sectionIndex]['columns'][$columnIndex]['blocks'] = $blocks;
        $this->isDirty = true;
    }

    public function removeBlock(string $blockId): void
    {
        foreach ($this->sections as $sIdx => $section) {
            foreach ($section['columns'] as $cIdx => $column) {
                foreach ($column['blocks'] as $bIdx => $block) {
                    if (($block['id'] ?? null) === $blockId) {
                        unset($this->sections[$sIdx]['columns'][$cIdx]['blocks'][$bIdx]);
                        $this->sections[$sIdx]['columns'][$cIdx]['blocks'] = array_values(
                            $this->sections[$sIdx]['columns'][$cIdx]['blocks']
                        );
                        $this->isDirty = true;

                        return;
                    }
                }
            }
        }
    }

    /** Duplique un bloc juste après lui-même (nouvel id généré par normalize). */
    public function duplicateBlock(string $blockId): void
    {
        foreach ($this->sections as $sIdx => $section) {
            foreach ($section['columns'] as $cIdx => $column) {
                foreach ($column['blocks'] as $bIdx => $block) {
                    if (($block['id'] ?? null) === $blockId) {
                        $copy = $block;
                        unset($copy['id']); // normalize régénère un id unique
                        $copy = PageBuilderManager::normalize([
                            'sections' => [['layout' => '1col', 'columns' => [['blocks' => [$copy]]]]],
                        ])['sections'][0]['columns'][0]['blocks'][0] ?? null;
                        if ($copy === null) {
                            return;
                        }
                        $blocks = $this->sections[$sIdx]['columns'][$cIdx]['blocks'];
                        array_splice($blocks, $bIdx + 1, 0, [$copy]);
                        $this->sections[$sIdx]['columns'][$cIdx]['blocks'] = $blocks;
                        $this->isDirty = true;

                        return;
                    }
                }
            }
        }
    }

    public function updateCodeBlock(string $blockId, string $key, string $value): void
    {
        if (! in_array($key, ['language', 'content'], true)) {
            return;
        }
        $this->mutateBlock($blockId, 'code', function (array $block) use ($key, $value): array {
            $block[$key] = $value;

            return $block;
        }, renormalizeSection: $key === 'language');
    }

    public function updateImageAlt(string $blockId, string $alt): void
    {
        $this->mutateBlock($blockId, 'image', function (array $block) use ($alt): array {
            $block['alt'] = $alt;

            return $block;
        });
    }

    public function updateQuoteBlock(string $blockId, string $key, string $value): void
    {
        if (! in_array($key, ['text', 'cite'], true)) {
            return;
        }
        $this->mutateBlock($blockId, 'quote', function (array $block) use ($key, $value): array {
            $block[$key] = $value;

            return $block;
        });
    }

    public function updateButtonBlock(string $blockId, string $key, string $value): void
    {
        if (! in_array($key, ['label', 'url', 'variant'], true)) {
            return;
        }
        $this->mutateBlock($blockId, 'button', function (array $block) use ($key, $value): array {
            $block[$key] = $value;

            return $block;
        }, renormalizeSection: true);
    }

    public function updateSeparatorBlock(string $blockId, string $variant): void
    {
        $this->mutateBlock($blockId, 'separator', function (array $block) use ($variant): array {
            $block['variant'] = $variant;

            return $block;
        }, renormalizeSection: true);
    }

    public function updateCalloutBlock(string $blockId, string $key, string $value): void
    {
        if (! in_array($key, ['text', 'variant'], true)) {
            return;
        }
        $this->mutateBlock($blockId, 'callout', function (array $block) use ($key, $value): array {
            $block[$key] = $value;

            return $block;
        }, renormalizeSection: $key === 'variant');
    }

    public function updateTitleBlock(string $blockId, string $key, string $value): void
    {
        if (! in_array($key, ['level', 'text'], true)) {
            return;
        }
        $this->mutateBlock($blockId, 'title', function (array $block) use ($key, $value): array {
            $block[$key] = $value;

            return $block;
        }, renormalizeSection: $key === 'level');
    }

    public function updateVideoBlock(string $blockId, string $url): void
    {
        $this->mutateBlock($blockId, 'video', function (array $block) use ($url): array {
            $block['url'] = $url;

            return $block;
        }, renormalizeSection: true);
    }

    public function updateListStyle(string $blockId, string $style): void
    {
        $this->mutateBlock($blockId, 'list', function (array $block) use ($style): array {
            $block['style'] = $style;

            return $block;
        }, renormalizeSection: true);
    }

    /** Les items sont saisis un par ligne dans un textarea. */
    public function updateListItems(string $blockId, string $text): void
    {
        $items = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
        $this->mutateBlock($blockId, 'list', function (array $block) use ($items): array {
            $block['items'] = $items;

            return $block;
        }, renormalizeSection: true);
    }

    public function addAccordionItem(string $blockId): void
    {
        $this->mutateBlock($blockId, 'accordion', function (array $block): array {
            $block['items'][] = ['q' => '', 'a' => ''];

            return $block;
        });
    }

    public function removeAccordionItem(string $blockId, int $index): void
    {
        $this->mutateBlock($blockId, 'accordion', function (array $block) use ($index): array {
            if (isset($block['items'][$index])) {
                unset($block['items'][$index]);
                $block['items'] = array_values($block['items']);
            }

            return $block;
        });
    }

    public function updateAccordionItem(string $blockId, int $index, string $key, string $value): void
    {
        if (! in_array($key, ['q', 'a'], true)) {
            return;
        }
        $this->mutateBlock($blockId, 'accordion', function (array $block) use ($index, $key, $value): array {
            if (isset($block['items'][$index])) {
                $block['items'][$index][$key] = $value;
            }

            return $block;
        }, renormalizeSection: true);
    }

    public function updateGalleryColumns(string $blockId, int $columns): void
    {
        $this->mutateBlock($blockId, 'gallery', function (array $block) use ($columns): array {
            $block['columns'] = $columns;

            return $block;
        }, renormalizeSection: true);
    }

    public function openGalleryPicker(string $blockId): void
    {
        $this->pickingGalleryBlockId = $blockId;
        $current = $this->findBlock($blockId)['media_ids'] ?? [];
        $this->dispatch(
            'open-media-picker-modal',
            statePath: 'pko-page-builder-gallery',
            multiple: true,
            preselected: array_values($current),
            mediagroup: 'page-builder',
            folder: null,
        );
    }

    public function removeGalleryImage(string $blockId, int $mediaId): void
    {
        $this->mutateBlock($blockId, 'gallery', function (array $block) use ($mediaId): array {
            $block['media_ids'] = array_values(array_filter($block['media_ids'] ?? [], fn ($id) => (int) $id !== $mediaId));

            return $block;
        });
    }

    /**
     * Applique $mutator au bloc ($blockId, $expectedType) et re-normalise
     * éventuellement la section porteuse pour ré-appliquer les allowlists.
     *
     * @param  callable(array<string,mixed>): array<string,mixed>  $mutator
     */
    private function mutateBlock(string $blockId, string $expectedType, callable $mutator, bool $renormalizeSection = false): void
    {
        foreach ($this->sections as $sIdx => $section) {
            foreach ($section['columns'] as $cIdx => $column) {
                foreach ($column['blocks'] as $bIdx => $block) {
                    if (($block['id'] ?? null) === $blockId && ($block['type'] ?? null) === $expectedType) {
                        $this->sections[$sIdx]['columns'][$cIdx]['blocks'][$bIdx] = $mutator($block);
                        if ($renormalizeSection) {
                            $this->sections[$sIdx] = PageBuilderManager::normalize(['sections' => [$this->sections[$sIdx]]])['sections'][0];
                        }
                        $this->isDirty = true;

                        return;
                    }
                }
            }
        }
    }

    // ---------- Text block — Filament Action + TiptapEditor

    public function editTextAction(): Action
    {
        return Action::make('editText')
            ->label('Modifier le texte')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading('Éditer le bloc de texte')
            ->modalWidth('5xl')
            ->fillForm(fn (array $arguments): array => [
                'html' => (string) ($this->findBlock($arguments['blockId'] ?? '')['html'] ?? ''),
            ])
            ->form([
                TiptapEditor::make('html')
                    ->label('Contenu')
                    ->maxContentWidth('full')
                    ->disableFloatingMenus()
                    ->tools([
                        'heading', 'bold', 'italic', 'underline', '|',
                        'bullet-list', 'ordered-list', 'blockquote', 'hr', '|',
                        'link', 'source',
                    ])
                    ->output(TiptapOutput::Html),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->mutateBlock((string) ($arguments['blockId'] ?? ''), 'text', function (array $block) use ($data): array {
                    $block['html'] = (string) ($data['html'] ?? '');

                    return $block;
                });
            });
    }

    // ---------- Image / cover — media picker (événement partagé avec pko-product)

    public function openImagePicker(string $blockId): void
    {
        $this->pickingImageBlockId = $blockId;
        $this->dispatch(
            'open-media-picker-modal',
            statePath: 'pko-page-builder-image',
            multiple: false,
            preselected: [],
            mediagroup: 'page-builder',
            folder: null,
        );
    }

    public function openCoverPicker(): void
    {
        $this->dispatch(
            'open-media-picker-modal',
            statePath: 'pko-page-builder-cover',
            multiple: false,
            preselected: $this->coverMediaId ? [$this->coverMediaId] : [],
            mediagroup: 'cover',
            folder: 'blog',
        );
    }

    public function clearCover(): void
    {
        $this->coverMediaId = null;
        $this->coverUrl = null;
        $this->isDirty = true;
    }

    /**
     * Livewire 3 injecte les params nommés dispatchés par `PkoMediaLibrary::confirm()` :
     *   dispatch('media-picked', statePath: ..., ids: [...], medias: [...])
     *
     * @param  array<int, int>  $ids
     * @param  array<int, array{id:int,url:string,alt:string,fileName:string}>  $medias
     */
    #[On('media-picked')]
    public function onMediaPicked(string $statePath = '', array $ids = [], array $medias = []): void
    {
        $first = $medias[0] ?? null;

        if ($statePath === 'pko-page-builder-cover') {
            if (is_array($first)) {
                $this->coverMediaId = (int) ($first['id'] ?? 0) ?: null;
                $this->coverUrl = (string) ($first['url'] ?? '') ?: null;
                $this->isDirty = true;
            }

            return;
        }

        if ($statePath === 'pko-page-builder-gallery') {
            if ($this->pickingGalleryBlockId !== null) {
                $blockId = $this->pickingGalleryBlockId;
                $this->pickingGalleryBlockId = null;
                $mediaIds = array_values(array_map('intval', $ids));
                $this->mutateBlock($blockId, 'gallery', function (array $block) use ($mediaIds): array {
                    $block['media_ids'] = $mediaIds;

                    return $block;
                });
            }

            return;
        }

        if ($statePath !== 'pko-page-builder-image' || $this->pickingImageBlockId === null) {
            return;
        }

        $blockId = $this->pickingImageBlockId;
        $this->pickingImageBlockId = null;

        if (! is_array($first)) {
            return;
        }

        $this->mutateBlock($blockId, 'image', function (array $block) use ($first): array {
            $block['media_id'] = (int) ($first['id'] ?? 0);
            $block['url'] = (string) ($first['url'] ?? '');
            if (empty($block['alt'])) {
                $block['alt'] = (string) ($first['alt'] ?? '');
            }

            return $block;
        });
    }

    // ---------- Save / publish / delete

    public function save(): void
    {
        $this->persist();
        $this->dispatch('notify', title: 'Contenu enregistré', type: 'success');
    }

    public function publish(): void
    {
        if ($this->withMeta) {
            $this->status = 'published';
            if (! $this->publishedAt) {
                $this->publishedAt = now()->format('Y-m-d\TH:i');
            }
        }
        $this->persist();
        $this->dispatch('notify', title: 'Contenu publié', type: 'success');
    }

    private function persist(): void
    {
        $record = $this->loadRecord();

        if ($this->withMeta) {
            $this->validateMeta();

            $record->post_type_id = $this->postTypeId;
            $record->title = trim($this->title);
            $record->slug = Str::slug($this->slug !== '' ? $this->slug : $this->title);
            $record->excerpt = $this->excerpt;
            $record->status = in_array($this->status, ['draft', 'published'], true) ? $this->status : 'draft';
            $record->published_at = $this->publishedAt ? Carbon::parse($this->publishedAt) : null;
            $record->seo_title = $this->seoTitle;
            $record->seo_description = $this->seoDescription;
        }

        $record->content = PageBuilderManager::normalize([
            'heading' => $this->heading,
            'sections' => $this->sections,
        ]);
        $record->save();

        if ($this->withMeta && method_exists($record, 'syncMediaAttachments')) {
            $record->syncMediaAttachments($this->coverMediaId ? [$this->coverMediaId] : [], 'cover');
            $this->coverUrl = $record->firstMediaUrl('cover');
            // Le nom en base peut avoir été re-slugé : re-synchro l'état local.
            $this->slug = (string) $record->slug;
        }

        $this->isDirty = false;
    }

    private function validateMeta(): void
    {
        $this->validate([
            'postTypeId' => ['required', 'integer', Rule::exists('pko_post_types', 'id')],
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200'],
            'status' => ['required', 'in:draft,published'],
            'seoTitle' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'seoDescription' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'postTypeId' => 'type de contenu',
            'title' => 'titre',
            'slug' => 'slug',
        ]);

        $slug = Str::slug($this->slug !== '' ? $this->slug : $this->title);
        $exists = $this->modelClass::query()
            ->where('slug', $slug)
            ->where('post_type_id', $this->postTypeId)
            ->whereKeyNot($this->recordId)
            ->exists();

        if ($exists) {
            $this->addError('slug', 'Ce slug est déjà utilisé pour ce type de contenu.');
            throw ValidationException::withMessages([
                'slug' => 'Ce slug est déjà utilisé pour ce type de contenu.',
            ]);
        }
    }

    public function deleteRecord(): mixed
    {
        if (! $this->withMeta) {
            return null;
        }
        $this->loadRecord()->delete();
        $this->dispatch('notify', title: 'Contenu supprimé', type: 'success');

        return $this->indexUrl ? $this->redirect($this->indexUrl) : null;
    }

    private function loadRecord(): Model
    {
        /** @var Model $model */
        $model = app($this->modelClass);

        return $model::query()->findOrFail($this->recordId);
    }
}
