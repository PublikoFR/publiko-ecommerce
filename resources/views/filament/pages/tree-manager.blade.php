<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"
            defer></script>

    <div class="grid gap-6"
         @if($activeTab === 'both') style="grid-template-columns: repeat(2, minmax(0, 1fr))" @endif
         x-data="treeManager()"
         x-init="boot()">

        {{-- ============================================================ --}}
        {{-- CATÉGORIES                                                   --}}
        {{-- ============================================================ --}}
        @if (in_array($activeTab, ['categories', 'both']))
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-rectangle-stack class="h-5 w-5 text-primary-500" />
                    Catégories
                    <span class="ml-auto text-xs font-normal text-gray-500">
                        {{ count($collectionsTree) }} racine(s)
                    </span>
                </div>
            </x-slot>

            <x-slot name="headerEnd">
                <div class="flex items-center gap-1">
                    <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray"
                        wire:click="mountAction('exportCollections')">
                        Export
                    </x-filament::button>
                    <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-up-tray"
                        wire:click="mountAction('importCollections')">
                        Import
                    </x-filament::button>
                    <x-filament::button size="sm" icon="heroicon-o-plus"
                        wire:click="mountAction('createCollectionAction')">
                        Ajouter
                    </x-filament::button>
                </div>
            </x-slot>

            <div class="space-y-3">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        placeholder="Rechercher une catégorie…"
                        wire:model.live.debounce.300ms="collectionSearch" />
                </x-filament::input.wrapper>

                @if (count($collectionsTree) === 0)
                    <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700">
                        Aucune catégorie. Cliquez sur « Ajouter ».
                    </div>
                @else
                    <ul class="tree-list tree-list--collections space-y-1"
                        data-sortable="collections"
                        data-parent-id="">
                        @foreach ($collectionsTree as $node)
                            @include('filament.pages.tree-manager.collection-node', ['node' => $node, 'depth' => 0])
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-filament::section>
        @endif

        {{-- ============================================================ --}}
        {{-- CARACTÉRISTIQUES                                             --}}
        {{-- ============================================================ --}}
        @if (in_array($activeTab, ['features', 'both']))
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-tag class="h-5 w-5 text-primary-500" />
                    Caractéristiques
                    <span class="ml-auto text-xs font-normal text-gray-500">
                        {{ count($featureFamilies) }} famille(s)
                    </span>
                </div>
            </x-slot>

            <x-slot name="headerEnd">
                <div class="flex items-center gap-1">
                    <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray"
                        wire:click="mountAction('exportFeatures')">
                        Export
                    </x-filament::button>
                    <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-up-tray"
                        wire:click="mountAction('importFeatures')">
                        Import
                    </x-filament::button>
                    <x-filament::button size="sm" icon="heroicon-o-plus"
                        wire:click="mountAction('createFamilyAction')">
                        Ajouter
                    </x-filament::button>
                </div>
            </x-slot>

            <div class="space-y-3">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        placeholder="Rechercher une caractéristique…"
                        wire:model.live.debounce.300ms="featureSearch" />
                </x-filament::input.wrapper>

                @if (count($featureFamilies) === 0)
                    <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700">
                        Aucune famille. Cliquez sur « Ajouter ».
                    </div>
                @else
                    <ul class="tree-list tree-list--families space-y-2"
                        data-sortable="families">
                        @foreach ($featureFamilies as $family)
                            @include('filament.pages.tree-manager.feature-family', ['family' => $family])
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-filament::section>
        @endif
    </div>

    <style>
        .tree-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .tree-node {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            background-color: rgb(249 250 251);
            border: 1px solid rgb(229 231 235);
            transition: background-color 0.15s;
        }
        .dark .tree-node {
            background-color: rgb(31 41 55);
            border-color: rgb(55 65 81);
        }
        .tree-node:hover {
            background-color: rgb(243 244 246);
        }
        .dark .tree-node:hover {
            background-color: rgb(55 65 81);
        }
        .tree-node__handle {
            cursor: grab;
            color: rgb(156 163 175);
            flex-shrink: 0;
        }
        .tree-node__handle:active {
            cursor: grabbing;
        }
        .tree-node__label {
            flex: 1;
            min-width: 0;
            font-size: 0.875rem;
            color: rgb(17 24 39);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .dark .tree-node__label {
            color: rgb(243 244 246);
        }
        .tree-node__badge {
            font-size: 0.7rem;
            color: rgb(107 114 128);
            background-color: rgb(229 231 235);
            padding: 0.1rem 0.4rem;
            border-radius: 9999px;
            flex-shrink: 0;
        }
        .dark .tree-node__badge {
            background-color: rgb(55 65 81);
            color: rgb(209 213 219);
        }
        .tree-node__actions {
            display: flex;
            gap: 0.25rem;
            opacity: 0;
            transition: opacity 0.15s;
            flex-shrink: 0;
        }
        .tree-node:hover .tree-node__actions {
            opacity: 1;
        }
        .tree-node__action {
            padding: 0.25rem;
            border-radius: 0.25rem;
            color: rgb(107 114 128);
        }
        .tree-node__action:hover {
            background-color: rgb(229 231 235);
            color: rgb(17 24 39);
        }
        .dark .tree-node__action:hover {
            background-color: rgb(55 65 81);
            color: rgb(243 244 246);
        }
        .tree-node__action--danger:hover {
            color: rgb(220 38 38);
        }
        .tree-node__thumb {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 0.25rem;
            object-fit: cover;
            flex-shrink: 0;
        }
        .tree-children {
            list-style: none;
            padding-left: 1.5rem;
            margin-top: 0.25rem;
            border-left: 2px dashed rgb(229 231 235);
            margin-left: 0.75rem;
        }
        .dark .tree-children {
            border-left-color: rgb(55 65 81);
        }
        .tree-list .sortable-ghost {
            opacity: 0.4;
            background-color: rgb(219 234 254);
        }
        .tree-list .sortable-drag {
            background-color: rgb(255 255 255);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        @media (max-width: 1023px) {
            .grid[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>

    @script
    <script>
        Alpine.data('treeManager', () => ({
            instances: [],

            boot() {
                this.initAll();
                // Re-init after Livewire updates DOM
                Livewire.hook('morph.updated', ({ el, component }) => {
                    this.$nextTick(() => this.initAll());
                });
            },

            destroyAll() {
                this.instances.forEach((i) => {
                    try { i.destroy(); } catch (e) { /* no-op */ }
                });
                this.instances = [];
            },

            initAll() {
                if (typeof Sortable === 'undefined') {
                    // SortableJS not yet loaded (defer). Retry shortly.
                    setTimeout(() => this.initAll(), 150);
                    return;
                }
                this.destroyAll();
                this.$root.querySelectorAll('[data-sortable]').forEach((el) => {
                    this.instances.push(this.makeSortable(el));
                });
            },

            makeSortable(el) {
                const kind = el.dataset.sortable;
                return new Sortable(el, {
                    group: (kind === 'collections' || kind === 'collection-children')
                        ? { name: 'collections-tree', pull: true, put: true }
                        : (kind === 'values' || kind === 'values-any')
                            ? { name: 'features-values', pull: true, put: true }
                            : kind,
                    handle: '.tree-node__handle',
                    animation: 150,
                    fallbackOnBody: true,
                    invertSwap: true,
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    onEnd: (evt) => this.handleEnd(kind, evt),
                });
            },

            handleEnd(kind, evt) {
                const item = evt.item;
                const id = parseInt(item.dataset.id, 10);
                const newIndex = evt.newIndex;

                if (kind === 'collections' || kind === 'collection-children') {
                    const parentEl = evt.to;
                    const parentRaw = parentEl.dataset.parentId;
                    const parentId = parentRaw === '' || parentRaw === undefined
                        ? null
                        : parseInt(parentRaw, 10);
                    this.$wire.moveCollection(id, parentId, newIndex);
                    return;
                }

                if (kind === 'families') {
                    this.$wire.moveFeatureFamily(id, newIndex);
                    return;
                }

                if (kind === 'values' || kind === 'values-any') {
                    const newFamilyId = parseInt(evt.to.dataset.familyId, 10);
                    this.$wire.moveFeatureValue(id, newFamilyId, newIndex);
                    return;
                }
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
