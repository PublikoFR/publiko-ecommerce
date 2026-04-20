<div x-data="{ previewOpen: false }" class="relative">

    {{-- ================================================================= --}}
    {{-- TOOLBAR                                                           --}}
    {{-- ================================================================= --}}
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold">Contenu</h2>
        <div class="flex items-center gap-2">
            <button
                type="button"
                x-on:click="previewOpen = true"
                class="inline-flex items-center gap-1 rounded border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M10 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/></svg>
                Aperçu
            </button>
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
    </div>

    {{-- ================================================================= --}}
    {{-- LAYOUT : PALETTE à gauche — ÉDITEUR full width                    --}}
    {{-- ================================================================= --}}
    <div class="grid grid-cols-1 lg:grid-cols-[200px_minmax(0,1fr)] gap-4">

        {{-- PALETTE --}}
        <aside class="space-y-3 lg:sticky lg:top-4 lg:self-start">
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sections</h3>
                <div x-pb-palette class="mt-2 space-y-1">
                    <div data-palette-type="section-1col" class="cursor-grab rounded border border-dashed border-gray-300 px-2 py-1.5 text-xs text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300">
                        <span class="font-medium">Section 1 colonne</span>
                    </div>
                    <div data-palette-type="section-2col" class="cursor-grab rounded border border-dashed border-gray-300 px-2 py-1.5 text-xs text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300">
                        <span class="font-medium">Section 2 colonnes</span>
                    </div>
                    <div data-palette-type="section-3col" class="cursor-grab rounded border border-dashed border-gray-300 px-2 py-1.5 text-xs text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300">
                        <span class="font-medium">Section 3 colonnes</span>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Blocs</h3>
                <div x-pb-palette class="mt-2 space-y-1">
                    <div data-palette-type="text" class="flex cursor-grab items-center gap-2 rounded border border-dashed border-gray-300 px-2 py-1.5 text-xs text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M3 5a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Zm0 4a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Zm0 4a1 1 0 0 1 1-1h8a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Z"/></svg>
                        <span class="font-medium">Texte</span>
                    </div>
                    <div data-palette-type="image" class="flex cursor-grab items-center gap-2 rounded border border-dashed border-gray-300 px-2 py-1.5 text-xs text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M1 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V5Zm3 9.5 3.5-3.5 2.5 2.5 4-4 4 4V15a1 1 0 0 1-1 1H4a1 1 0 0 1-.5-.5ZM7 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                        <span class="font-medium">Image</span>
                    </div>
                    <div data-palette-type="code" class="flex cursor-grab items-center gap-2 rounded border border-dashed border-gray-300 px-2 py-1.5 text-xs text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M12 4a1 1 0 1 0-2 0v12a1 1 0 1 0 2 0V4Zm2.293 2.293a1 1 0 0 1 1.414 0l3 3a1 1 0 0 1 0 1.414l-3 3a1 1 0 0 1-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 0 1 0-1.414Zm-8.586 0a1 1 0 0 1 0 1.414L3.414 10l2.293 2.293a1 1 0 1 1-1.414 1.414l-3-3a1 1 0 0 1 0-1.414l3-3a1 1 0 0 1 1.414 0Z" clip-rule="evenodd"/></svg>
                        <span class="font-medium">Code</span>
                    </div>
                </div>
            </div>

            <p class="text-[11px] leading-snug text-gray-500 dark:text-gray-400">
                Glisse un élément dans la zone de droite. Les sections peuvent être réordonnées via leur poignée de gauche.
            </p>
        </aside>

        {{-- ÉDITEUR --}}
        <main class="min-w-0">
            @php
                // Empty state offre aussi une drop-zone pour accueillir la 1re section.
                $hasSections = count($sections) > 0;
            @endphp

            {{-- drop-zone principale pour les sections --}}
            <div
                x-pb-sortable="reorderSections"
                data-handle=".pko-section-handle"
                class="space-y-4"
            >
                @foreach ($sections as $sIndex => $section)
                    <div
                        wire:key="section-{{ $section['id'] }}"
                        data-id="{{ $section['id'] }}"
                        class="rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900/40"
                    >
                        {{-- Header --}}
                        <div class="flex items-center gap-2 border-b border-gray-200 px-3 py-2 dark:border-white/10">
                            <button type="button" class="pko-section-handle cursor-grab text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="Glisser pour réordonner">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path d="M7 4a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm6-10a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/></svg>
                            </button>
                            <span class="text-sm font-medium">Section {{ $sIndex + 1 }}</span>

                            <div class="ml-auto flex items-center gap-1">
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

                        {{-- Settings section --}}
                        <details class="border-b border-gray-100 px-3 py-2 dark:border-white/10">
                            <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400">
                                Style (padding, margin, couleurs)
                            </summary>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-xs md:grid-cols-4 xl:grid-cols-6">
                                @foreach (['t' => 'Padding ↑', 'r' => 'Padding →', 'b' => 'Padding ↓', 'l' => 'Padding ←'] as $side => $label)
                                    <label class="flex flex-col gap-1">
                                        <span class="text-gray-500">{{ $label }} (px)</span>
                                        <input type="number" min="0" max="400"
                                            value="{{ $section['padding'][$side] }}"
                                            wire:change="updateSectionPadding({{ $sIndex }}, '{{ $side }}', parseInt($event.target.value) || 0)"
                                            class="w-full rounded border border-gray-300 px-2 py-1 dark:border-white/10 dark:bg-gray-900"
                                        />
                                    </label>
                                @endforeach
                                @foreach (['t' => 'Margin ↑', 'b' => 'Margin ↓'] as $side => $label)
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
                                    <span class="text-gray-500">Fond</span>
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
                                    <span class="text-gray-500">Texte</span>
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
                                <div
                                    x-pb-drop
                                    data-drop-type="blocks"
                                    data-section-index="{{ $sIndex }}"
                                    data-column-index="{{ $cIndex }}"
                                    class="min-h-[80px] space-y-2 rounded border border-dashed border-gray-200 p-2 dark:border-white/10"
                                >
                                    @foreach ($column['blocks'] as $block)
                                        <div wire:key="block-{{ $block['id'] }}" data-id="{{ $block['id'] }}" class="rounded border border-gray-200 bg-gray-50 p-2 dark:border-white/10 dark:bg-white/5">
                                            @include('page-builder::livewire._block-editor', ['block' => $block])
                                        </div>
                                    @endforeach

                                    @if (empty($column['blocks']))
                                        <div class="flex h-full items-center justify-center rounded border border-dashed border-gray-300 py-3 text-[11px] text-gray-400 dark:border-white/10">
                                            Glissez un bloc ici
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Drop-zone en bas pour ajouter une section --}}
            <div
                x-pb-drop
                data-drop-type="sections"
                @class([
                    'mt-4 flex items-center justify-center rounded-lg border-2 border-dashed p-6 text-sm text-gray-500 transition',
                    'border-gray-300 hover:border-primary-400 hover:text-primary-700' => $hasSections,
                    'border-gray-400 bg-gray-50 dark:bg-white/5' => ! $hasSections,
                ])
            >
                @if ($hasSections)
                    Glissez une section depuis la palette pour ajouter ici
                @else
                    Glissez une section depuis la palette pour commencer
                @endif
            </div>
        </main>
    </div>

    {{-- ================================================================= --}}
    {{-- PREVIEW SLIDE-OVER                                                --}}
    {{-- ================================================================= --}}
    <template x-teleport="body">
        <div
            x-show="previewOpen"
            x-transition.opacity
            x-on:keydown.escape.window="previewOpen = false"
            class="fixed inset-0 z-[60] bg-black/40"
            x-on:click.self="previewOpen = false"
            style="display: none"
        >
            <aside
                x-show="previewOpen"
                x-transition:enter="transition transform ease-out duration-200"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition transform ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="fixed inset-y-0 right-0 flex w-full max-w-2xl flex-col bg-white shadow-2xl dark:bg-gray-900"
            >
                <header class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <h2 class="text-base font-semibold">Aperçu</h2>
                    <button
                        type="button"
                        x-on:click="previewOpen = false"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto">
                    <x-page-builder::render :content="$this->tree" />
                </div>
            </aside>
        </div>
    </template>

    {{-- Modals Filament Actions --}}
    <x-filament-actions::modals />
</div>
