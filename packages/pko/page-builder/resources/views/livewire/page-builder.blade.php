<div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-4">
    {{-- =============================================================== --}}
    {{-- COLONNE GAUCHE — éditeur                                        --}}
    {{-- =============================================================== --}}
    <div class="space-y-4 min-w-0">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Contenu</h2>
            <button
                type="button"
                wire:click="save"
                @class([
                    'inline-flex items-center gap-1 rounded px-3 py-1.5 text-sm font-medium',
                    'bg-primary-600 text-white hover:bg-primary-700' => $isDirty,
                    'bg-gray-200 text-gray-500 cursor-not-allowed' => ! $isDirty,
                ])
                @disabled(! $isDirty)
            >
                Enregistrer
            </button>
        </div>

        @if (empty($sections))
            <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                Aucune section. Ajoutez-en une pour commencer.
            </div>
        @else
            <div x-data x-sortable="reorderSections" data-handle=".pko-section-handle" class="space-y-4">
                @foreach ($sections as $sIndex => $section)
                    <div
                        wire:key="section-{{ $section['id'] }}"
                        data-id="{{ $section['id'] }}"
                        class="rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900/40"
                    >
                        {{-- Header section --}}
                        <div class="flex items-center gap-2 border-b border-gray-200 px-3 py-2 dark:border-white/10">
                            <button type="button" class="pko-section-handle cursor-grab text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="Glisser pour réordonner">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path d="M7 4a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm6-10a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/></svg>
                            </button>

                            <span class="text-sm font-medium">Section {{ $sIndex + 1 }}</span>

                            <div class="ml-auto flex items-center gap-1">
                                {{-- Sélecteur layout --}}
                                @foreach (['1col' => '1', '2col' => '2', '3col' => '3'] as $layoutKey => $layoutLabel)
                                    <button
                                        type="button"
                                        wire:click="setSectionLayout({{ $sIndex }}, '{{ $layoutKey }}')"
                                        @class([
                                            'inline-flex items-center justify-center rounded border px-2 py-0.5 text-xs font-medium',
                                            'border-primary-600 bg-primary-50 text-primary-700' => $section['layout'] === $layoutKey,
                                            'border-gray-300 bg-white text-gray-700 hover:border-gray-400 dark:border-white/10 dark:bg-transparent dark:text-gray-300' => $section['layout'] !== $layoutKey,
                                        ])
                                        title="{{ $layoutLabel }} colonne{{ $layoutLabel > 1 ? 's' : '' }}"
                                    >
                                        {{ $layoutLabel }}col
                                    </button>
                                @endforeach

                                <button
                                    type="button"
                                    wire:click="removeSection({{ $sIndex }})"
                                    wire:confirm="Supprimer cette section ?"
                                    class="ml-1 text-gray-400 hover:text-danger-600"
                                    title="Supprimer la section"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M9 2a1 1 0 0 0-.894.553L7.382 4H4a1 1 0 1 0 0 2v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V6a1 1 0 1 0 0-2h-3.382l-.724-1.447A1 1 0 0 0 11 2H9Zm-2 6a1 1 0 1 1 2 0v7a1 1 0 1 1-2 0V8Zm5-1a1 1 0 0 0-1 1v7a1 1 0 1 0 2 0V8a1 1 0 0 0-1-1Z" clip-rule="evenodd"/></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Settings section (padding / margin / colors) --}}
                        <details class="border-b border-gray-100 px-3 py-2 dark:border-white/10">
                            <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400">
                                Style (padding, margin, couleurs)
                            </summary>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-xs md:grid-cols-4">
                                @foreach (['t' => 'Padding haut', 'r' => 'Padding droit', 'b' => 'Padding bas', 'l' => 'Padding gauche'] as $side => $label)
                                    <label class="flex flex-col gap-1">
                                        <span class="text-gray-500">{{ $label }} (px)</span>
                                        <input type="number" min="0" max="400"
                                            value="{{ $section['padding'][$side] }}"
                                            wire:change="updateSectionPadding({{ $sIndex }}, '{{ $side }}', parseInt($event.target.value) || 0)"
                                            class="w-full rounded border border-gray-300 px-2 py-1 dark:border-white/10 dark:bg-gray-900"
                                        />
                                    </label>
                                @endforeach
                                @foreach (['t' => 'Margin haut', 'b' => 'Margin bas'] as $side => $label)
                                    <label class="flex flex-col gap-1">
                                        <span class="text-gray-500">{{ $label }} (px)</span>
                                        <input type="number" min="0" max="400"
                                            value="{{ $section['margin'][$side] }}"
                                            wire:change="updateSectionMargin({{ $sIndex }}, '{{ $side }}', parseInt($event.target.value) || 0)"
                                            class="w-full rounded border border-gray-300 px-2 py-1 dark:border-white/10 dark:bg-gray-900"
                                        />
                                    </label>
                                @endforeach
                                <label class="flex flex-col gap-1">
                                    <span class="text-gray-500">Couleur de fond</span>
                                    <div class="flex items-center gap-1">
                                        <input type="color"
                                            value="{{ $section['background_color'] ?? '#ffffff' }}"
                                            wire:change="updateSectionColor({{ $sIndex }}, 'background_color', $event.target.value)"
                                            class="h-7 w-10 cursor-pointer rounded border border-gray-300 dark:border-white/10"
                                        />
                                        <button type="button"
                                            wire:click="updateSectionColor({{ $sIndex }}, 'background_color', null)"
                                            class="text-[11px] text-gray-400 hover:text-gray-600"
                                        >reset</button>
                                    </div>
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span class="text-gray-500">Couleur du texte</span>
                                    <div class="flex items-center gap-1">
                                        <input type="color"
                                            value="{{ $section['text_color'] ?? '#000000' }}"
                                            wire:change="updateSectionColor({{ $sIndex }}, 'text_color', $event.target.value)"
                                            class="h-7 w-10 cursor-pointer rounded border border-gray-300 dark:border-white/10"
                                        />
                                        <button type="button"
                                            wire:click="updateSectionColor({{ $sIndex }}, 'text_color', null)"
                                            class="text-[11px] text-gray-400 hover:text-gray-600"
                                        >reset</button>
                                    </div>
                                </label>
                            </div>
                        </details>

                        {{-- Columns + blocks --}}
                        <div @class([
                            'grid gap-3 p-3',
                            'md:grid-cols-1' => $section['layout'] === '1col',
                            'md:grid-cols-2' => $section['layout'] === '2col',
                            'md:grid-cols-3' => $section['layout'] === '3col',
                        ])>
                            @foreach ($section['columns'] as $cIndex => $column)
                                <div class="space-y-2 rounded border border-dashed border-gray-200 p-2 dark:border-white/10">
                                    @foreach ($column['blocks'] as $block)
                                        <div wire:key="block-{{ $block['id'] }}" class="rounded border border-gray-200 bg-gray-50 p-2 dark:border-white/10 dark:bg-white/5">
                                            @include('page-builder::livewire._block-editor', ['block' => $block])
                                        </div>
                                    @endforeach

                                    {{-- Add block --}}
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button"
                                            wire:click="addBlock({{ $sIndex }}, {{ $cIndex }}, 'text')"
                                            class="inline-flex items-center gap-1 rounded border border-dashed border-gray-300 px-2 py-1 text-xs text-gray-600 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300"
                                        >
                                            + Texte
                                        </button>
                                        <button type="button"
                                            wire:click="addBlock({{ $sIndex }}, {{ $cIndex }}, 'image')"
                                            class="inline-flex items-center gap-1 rounded border border-dashed border-gray-300 px-2 py-1 text-xs text-gray-600 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300"
                                        >
                                            + Image
                                        </button>
                                        <button type="button"
                                            wire:click="addBlock({{ $sIndex }}, {{ $cIndex }}, 'code')"
                                            class="inline-flex items-center gap-1 rounded border border-dashed border-gray-300 px-2 py-1 text-xs text-gray-600 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300"
                                        >
                                            + Code
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex items-center gap-2">
            <button type="button" wire:click="addSection('1col')"
                class="inline-flex items-center gap-1 rounded border border-dashed border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300">
                + Ajouter une section 1 colonne
            </button>
            <button type="button" wire:click="addSection('2col')"
                class="inline-flex items-center gap-1 rounded border border-dashed border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300">
                + 2 colonnes
            </button>
            <button type="button" wire:click="addSection('3col')"
                class="inline-flex items-center gap-1 rounded border border-dashed border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300">
                + 3 colonnes
            </button>
        </div>
    </div>

    {{-- =============================================================== --}}
    {{-- COLONNE DROITE — preview live                                   --}}
    {{-- =============================================================== --}}
    <div class="space-y-2">
        <h2 class="text-lg font-semibold">Aperçu</h2>
        <div class="sticky top-4 rounded-lg border border-gray-200 bg-white overflow-hidden dark:border-white/10 dark:bg-gray-900">
            <div class="max-h-[80vh] overflow-y-auto">
                <x-page-builder::render :content="$this->tree" />
            </div>
        </div>
    </div>

    {{-- Modals Filament Actions --}}
    <x-filament-actions::modals />
</div>
