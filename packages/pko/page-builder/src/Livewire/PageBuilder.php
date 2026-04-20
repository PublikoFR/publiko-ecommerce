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
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Pko\PageBuilder\Services\PageBuilderManager;

/**
 * Editor Livewire autonome pour un contenu page-builder. Se monte dans une
 * Filament Page custom avec en props :
 *   - $modelClass : FQCN du modèle (Pko\StorefrontCms\Models\Page|Post)
 *   - $recordId   : id du record à éditer
 *
 * Le state est 100% dans $this->sections (shape normalisée par
 * PageBuilderManager::normalize). Les mutations passent toutes par
 * PageBuilderManager::newSection / newBlock pour garantir les defaults.
 *
 * Interactions :
 *   - Text : Filament Action "editText" avec un form TiptapEditor
 *   - Image : dispatch open-media-picker-modal (événement existant du projet),
 *             listener #[On('media-picked')] route la sélection vers le bloc courant
 *   - Code : édition inline via wire:model sur language et content
 */
class PageBuilder extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** @var class-string<Model> */
    public string $modelClass;

    public int $recordId;

    /** @var array<int, array<string, mixed>> */
    public array $sections = [];

    public bool $isDirty = false;

    /** Identifiant du bloc image en cours de sélection (pour le media picker). */
    public ?string $pickingImageBlockId = null;

    /** @param  class-string<Model>  $modelClass */
    public function mount(string $modelClass, int $recordId): void
    {
        $this->modelClass = $modelClass;
        $this->recordId = $recordId;

        $record = $this->loadRecord();
        $this->sections = PageBuilderManager::normalize($record->content ?? null)['sections'];
    }

    public function render(): View
    {
        return view('page-builder::livewire.page-builder');
    }

    // ---------- State getters

    /**
     * @return array{sections: array<int, array<string, mixed>>}
     */
    public function getTreeProperty(): array
    {
        return ['sections' => $this->sections];
    }

    /** @return array<string, mixed>|null */
    private function findBlock(string $blockId, bool $returnRefs = false, ?array &$sectionRef = null, ?array &$columnRef = null): ?array
    {
        foreach ($this->sections as $sIdx => $section) {
            foreach ($section['columns'] as $cIdx => $column) {
                foreach ($column['blocks'] as $bIdx => $block) {
                    if (($block['id'] ?? null) === $blockId) {
                        if ($returnRefs) {
                            $sectionRef = ['index' => $sIdx];
                            $columnRef = ['index' => $cIdx, 'block_index' => $bIdx];
                        }

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

    public function updateCodeBlock(string $blockId, string $key, string $value): void
    {
        if (! in_array($key, ['language', 'content'], true)) {
            return;
        }
        foreach ($this->sections as $sIdx => $section) {
            foreach ($section['columns'] as $cIdx => $column) {
                foreach ($column['blocks'] as $bIdx => $block) {
                    if (($block['id'] ?? null) === $blockId && ($block['type'] ?? null) === 'code') {
                        $this->sections[$sIdx]['columns'][$cIdx]['blocks'][$bIdx][$key] = $value;
                        if ($key === 'language') {
                            // re-normalise pour enforcer l'allowlist
                            $norm = PageBuilderManager::normalize(['sections' => [$this->sections[$sIdx]]])['sections'][0];
                            $this->sections[$sIdx] = $norm;
                        }
                        $this->isDirty = true;

                        return;
                    }
                }
            }
        }
    }

    public function updateImageAlt(string $blockId, string $alt): void
    {
        foreach ($this->sections as $sIdx => $section) {
            foreach ($section['columns'] as $cIdx => $column) {
                foreach ($column['blocks'] as $bIdx => $block) {
                    if (($block['id'] ?? null) === $blockId && ($block['type'] ?? null) === 'image') {
                        $this->sections[$sIdx]['columns'][$cIdx]['blocks'][$bIdx]['alt'] = $alt;
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
                $this->writeTextBlock((string) ($arguments['blockId'] ?? ''), (string) ($data['html'] ?? ''));
            });
    }

    private function writeTextBlock(string $blockId, string $html): void
    {
        foreach ($this->sections as $sIdx => $section) {
            foreach ($section['columns'] as $cIdx => $column) {
                foreach ($column['blocks'] as $bIdx => $block) {
                    if (($block['id'] ?? null) === $blockId && ($block['type'] ?? null) === 'text') {
                        $this->sections[$sIdx]['columns'][$cIdx]['blocks'][$bIdx]['html'] = $html;
                        $this->isDirty = true;

                        return;
                    }
                }
            }
        }
    }

    // ---------- Image block — media picker (événement partagé avec pko-product)

    public function openImagePicker(string $blockId): void
    {
        $this->pickingImageBlockId = $blockId;
        $this->dispatch('open-media-picker-modal', statePath: 'pko-page-builder-image', multiple: false);
    }

    #[On('media-picked')]
    public function onMediaPicked(array $payload = [], ?string $statePath = null): void
    {
        if ($statePath !== 'pko-page-builder-image' || $this->pickingImageBlockId === null) {
            return;
        }
        $medias = $payload['medias'] ?? $payload;
        $first = is_array($medias) ? ($medias[0] ?? null) : null;
        if (! is_array($first)) {
            $this->pickingImageBlockId = null;

            return;
        }

        $blockId = $this->pickingImageBlockId;
        $this->pickingImageBlockId = null;

        foreach ($this->sections as $sIdx => $section) {
            foreach ($section['columns'] as $cIdx => $column) {
                foreach ($column['blocks'] as $bIdx => $block) {
                    if (($block['id'] ?? null) === $blockId && ($block['type'] ?? null) === 'image') {
                        $this->sections[$sIdx]['columns'][$cIdx]['blocks'][$bIdx]['media_id'] = (int) ($first['id'] ?? 0);
                        $this->sections[$sIdx]['columns'][$cIdx]['blocks'][$bIdx]['url'] = (string) ($first['url'] ?? '');
                        if (empty($this->sections[$sIdx]['columns'][$cIdx]['blocks'][$bIdx]['alt'])) {
                            $this->sections[$sIdx]['columns'][$cIdx]['blocks'][$bIdx]['alt'] = (string) ($first['alt'] ?? '');
                        }
                        $this->isDirty = true;

                        return;
                    }
                }
            }
        }
    }

    // ---------- Save

    public function save(): void
    {
        $record = $this->loadRecord();
        $record->content = PageBuilderManager::normalize(['sections' => $this->sections]);
        $record->save();
        $this->isDirty = false;

        $this->dispatch('notify', title: 'Contenu enregistré', type: 'success');
    }

    private function loadRecord(): Model
    {
        /** @var Model $model */
        $model = app($this->modelClass);

        return $model::query()->findOrFail($this->recordId);
    }
}
