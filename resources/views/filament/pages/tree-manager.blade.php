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
        <div>
            <div class="mb-2 flex items-center gap-2 px-1">
                <x-heroicon-o-rectangle-stack class="h-5 w-5 text-primary-500" />
                <span class="text-base font-semibold text-gray-950 dark:text-white">Catégories</span>
                <span class="ml-auto text-xs font-normal text-gray-500">
                    {{ count($this->collectionsTree) }} racine, {{ $this->countNodes($this->collectionsTree) }} au total
                </span>
            </div>
        <x-filament::section>
            <div class="mb-4 flex flex-wrap items-center justify-end gap-1">
                <x-filament::button size="sm" color="gray"
                    x-on:click="toggleCollapseAll('.tree-list--collections')">
                    <span class="flex items-center gap-1">
                        <x-heroicon-o-arrows-pointing-in class="h-4 w-4" x-cloak x-show="!collapsed['.tree-list--collections']" />
                        <x-heroicon-o-arrows-pointing-out class="h-4 w-4" x-cloak x-show="collapsed['.tree-list--collections']" />
                        <span x-text="collapsed['.tree-list--collections'] ? 'Tout déplier' : 'Tout réduire'"></span>
                    </span>
                </x-filament::button>
                <x-filament::button size="sm" color="gray"
                    title="Cocher / décocher toutes les catégories pour l'export (sans modifier leur activation)"
                    x-on:click="toggleAllExportSelected()">
                    <span class="flex items-center gap-1">
                        <x-heroicon-o-x-mark class="h-4 w-4" x-cloak x-show="allExportSelected" />
                        <x-heroicon-o-check class="h-4 w-4" x-cloak x-show="!allExportSelected" />
                        <span x-text="allExportSelected ? 'Tout décocher' : 'Tout cocher'"></span>
                    </span>
                </x-filament::button>
                <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray"
                    x-on:click="$wire.set('collectionExportSelection', selectedExportIds()).then(() => $wire.mountAction('exportCollections'))">
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

            <div class="space-y-3">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        placeholder="Rechercher une catégorie…"
                        data-filter-target=".tree-list--collections"
                        x-on:input.debounce.200ms="filterTree($el, '.tree-list--collections')" />
                </x-filament::input.wrapper>

                @if (count($this->collectionsTree) === 0)
                    <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700">
                        Aucune catégorie. Cliquez sur « Ajouter ».
                    </div>
                @else
                    <ul class="tree-list tree-list--collections space-y-1"
                        data-sortable="collections"
                        data-parent-id="">
                        @foreach ($this->collectionsTree as $node)
                            @include('filament.pages.tree-manager.collection-node', ['node' => $node, 'depth' => 0])
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-filament::section>
        </div>
        @endif

        {{-- ============================================================ --}}
        {{-- CARACTÉRISTIQUES                                             --}}
        {{-- ============================================================ --}}
        @if (in_array($activeTab, ['features', 'both']))
        <div>
            <div class="mb-2 flex items-center gap-2 px-1">
                <x-heroicon-o-tag class="h-5 w-5 text-primary-500" />
                <span class="text-base font-semibold text-gray-950 dark:text-white">Caractéristiques</span>
                <span class="ml-auto text-xs font-normal text-gray-500">
                    {{ count($this->featureFamilies) }} familles, {{ $this->countValues($this->featureFamilies) }} valeurs
                </span>
            </div>
        <x-filament::section>
            <div class="mb-4 flex flex-wrap items-center justify-end gap-1">
                <x-filament::button size="sm" color="gray"
                    x-on:click="toggleCollapseAll('.tree-list--families')">
                    <span class="flex items-center gap-1">
                        <x-heroicon-o-arrows-pointing-in class="h-4 w-4" x-cloak x-show="!collapsed['.tree-list--families']" />
                        <x-heroicon-o-arrows-pointing-out class="h-4 w-4" x-cloak x-show="collapsed['.tree-list--families']" />
                        <span x-text="collapsed['.tree-list--families'] ? 'Tout déplier' : 'Tout réduire'"></span>
                    </span>
                </x-filament::button>
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

            <div class="space-y-3">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        placeholder="Rechercher une caractéristique…"
                        data-filter-target=".tree-list--families"
                        x-on:input.debounce.200ms="filterTree($el, '.tree-list--families')" />
                </x-filament::input.wrapper>

                @if (count($this->featureFamilies) === 0)
                    <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700">
                        Aucune famille. Cliquez sur « Ajouter ».
                    </div>
                @else
                    <ul class="tree-list tree-list--families space-y-2"
                        data-sortable="families">
                        @foreach ($this->featureFamilies as $family)
                            @include('filament.pages.tree-manager.feature-family', ['family' => $family])
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-filament::section>
        </div>
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
            gap: 0.375rem;
            padding: 0.1875rem 0.5rem;
            border-radius: 0.375rem;
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
            min-width: 0;
            font-size: 0.8125rem;
            line-height: 1.25;
            color: rgb(17 24 39);
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .tree-node__label .tree-node__badge {
            margin-left: 0.25rem;
            vertical-align: middle;
        }
        .dark .tree-node__label {
            color: rgb(243 244 246);
        }
        .tree-node__badge {
            font-size: 0.65rem;
            color: rgb(107 114 128);
            background-color: rgb(229 231 235);
            padding: 0.05rem 0.35rem;
            border-radius: 9999px;
            flex-shrink: 0;
        }
        .dark .tree-node__badge {
            background-color: rgb(55 65 81);
            color: rgb(209 213 219);
        }
        [x-cloak] {
            display: none !important;
        }
        .tree-node__badge--browse {
            background-color: rgb(224 231 255);
            color: rgb(55 48 163);
        }
        .dark .tree-node__badge--browse {
            background-color: rgb(49 46 129);
            color: rgb(199 210 254);
        }
        .tree-node__menu-item small {
            display: block;
            font-size: 0.6875rem;
            opacity: 0.65;
        }
        .tree-node__thumb {
            object-fit: contain;
            border-radius: 0.1875rem;
        }
        .tree-node__actions {
            display: flex;
            gap: 0.25rem;
            opacity: 0;
            transition: opacity 0.15s;
            flex-shrink: 0;
            margin-left: auto;
            position: relative;
        }
        .tree-node:hover .tree-node__actions,
        .tree-node__actions.is-open {
            opacity: 1;
        }
        /* Ancré sous l'engrenage, sans interstice : un écart ferait remonter un
           mouseleave entre le bouton et le menu. z-index élevé car les nœuds
           suivants sont rendus après dans le flux. */
        .tree-node__menu {
            position: absolute;
            top: 100%;
            right: 0;
            z-index: 50;
            min-width: 13.5rem;
            padding: 0.25rem;
            border-radius: 0.5rem;
            background-color: rgb(255 255 255);
            border: 1px solid rgb(229 231 235);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }
        .dark .tree-node__menu {
            background-color: rgb(31 41 55);
            border-color: rgb(55 65 81);
        }
        .tree-node__menu-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.375rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.8125rem;
            line-height: 1.25;
            text-align: left;
            white-space: nowrap;
            color: rgb(55 65 81);
        }
        .dark .tree-node__menu-item {
            color: rgb(209 213 219);
        }
        .tree-node__menu-item:hover {
            background-color: rgb(243 244 246);
            color: rgb(17 24 39);
        }
        .dark .tree-node__menu-item:hover {
            background-color: rgb(55 65 81);
            color: rgb(243 244 246);
        }
        .tree-node__menu-item--danger {
            color: rgb(185 28 28);
        }
        .tree-node__menu-item--danger:hover {
            background-color: rgb(254 242 242);
            color: rgb(153 27 27);
        }
        .dark .tree-node__menu-item--danger {
            color: rgb(248 113 113);
        }
        .dark .tree-node__menu-item--danger:hover {
            background-color: rgb(127 29 29);
            color: rgb(254 226 226);
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
        .tree-node__toggle {
            padding: 0.125rem;
            border-radius: 0.25rem;
            color: rgb(107 114 128);
            flex-shrink: 0;
            transition: transform 0.15s;
        }
        .tree-node__toggle:hover {
            color: rgb(17 24 39);
        }
        .dark .tree-node__toggle:hover {
            color: rgb(243 244 246);
        }
        .tree-node__toggle {
            transform: rotate(90deg);
        }
        li.tree-collapsed > .tree-node .tree-node__toggle {
            transform: rotate(0deg);
        }
        .tree-children {
            list-style: none;
            padding-left: 1rem;
            margin-top: 0.125rem;
            border-left: 2px dashed rgb(229 231 235);
            margin-left: 0.5rem;
        }
        li.tree-collapsed > .tree-children {
            display: none;
        }
        .dark .tree-children {
            border-left-color: rgb(55 65 81);
        }
        mark.tree-hl {
            background-color: rgb(254 240 138);
            color: inherit;
        }
        .dark mark.tree-hl {
            background-color: rgb(133 77 14);
            color: rgb(254 240 138);
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
            sortableMap: new WeakMap(),
            collapsed: { '.tree-list--collections': false, '.tree-list--families': false },

            // Sélection des catégories à EXPORTER (indépendante de l'activation
            // storefront pko_enabled). Tout coché par défaut.
            exportSelected: Object.fromEntries(@js($this->allCollectionIds()).map(id => [String(id), true])),
            get allExportSelected() {
                const vals = Object.values(this.exportSelected);
                return vals.length > 0 && vals.every(Boolean);
            },
            toggleAllExportSelected() {
                const next = !this.allExportSelected;
                for (const k in this.exportSelected) this.exportSelected[k] = next;
            },
            selectedExportIds() {
                return Object.keys(this.exportSelected).filter(k => this.exportSelected[k]).map(Number);
            },

            toggleCollapseAll(selector) {
                const list = this.$root.querySelector(selector);
                if (!list) return;
                const next = !this.collapsed[selector];
                this.collapsed[selector] = next;
                list.querySelectorAll('li').forEach(li => {
                    if (li.querySelector(':scope > .tree-children')) {
                        li.classList.toggle('tree-collapsed', next);
                    }
                });
            },

            labelText(li) {
                const lbl = li.querySelector(':scope > .tree-node .tree-node__label');
                if (!lbl) return '';
                let t = '';
                lbl.childNodes.forEach(n => {
                    if (n.nodeType === 3) t += n.textContent;
                    else if (n.nodeType === 1 && !n.classList.contains('tree-node__badge')) t += n.textContent;
                });
                return t.toLowerCase();
            },

            filterTree(inputEl, listSelector) {
                const query = inputEl.value.toLowerCase().trim();
                const list = this.$root.querySelector(listSelector);
                if (!list) return;

                this.clearHighlights(list);

                const items = [...list.querySelectorAll('li')];

                if (!query) {
                    items.forEach(li => li.style.display = '');
                    return;
                }

                items.forEach(li => { li._m = this.labelText(li).includes(query); li._v = false; });

                items.forEach(li => {
                    if (!li._m) return;
                    li._v = true;
                    let p = li.parentElement?.closest('li');
                    while (p) { p._v = true; p = p.parentElement?.closest('li'); }
                });

                items.forEach(li => li.style.display = li._v ? '' : 'none');
                this.applyHighlights(list, query);
            },

            clearHighlights(root) {
                root.querySelectorAll('mark.tree-hl').forEach(m => m.replaceWith(...m.childNodes));
                root.querySelectorAll('.tree-node__label').forEach(el => el.normalize());
            },

            applyHighlights(list, query) {
                list.querySelectorAll('li:not([style*="none"]) > .tree-node .tree-node__label').forEach(lbl => {
                    this.hlWalk(lbl, query);
                });
            },

            hlWalk(el, q) {
                [...el.childNodes].forEach(n => {
                    if (n.nodeType === 1 && n.tagName !== 'MARK' && !n.classList.contains('tree-node__badge')) {
                        this.hlWalk(n, q);
                    } else if (n.nodeType === 3) {
                        let rest = n.textContent, lo = rest.toLowerCase(), i = lo.indexOf(q);
                        if (i === -1) return;
                        const f = document.createDocumentFragment();
                        while ((i = lo.indexOf(q)) !== -1) {
                            if (i > 0) f.appendChild(document.createTextNode(rest.slice(0, i)));
                            const m = document.createElement('mark');
                            m.className = 'tree-hl';
                            m.textContent = rest.slice(i, i + q.length);
                            f.appendChild(m);
                            rest = rest.slice(i + q.length);
                            lo = lo.slice(i + q.length);
                        }
                        if (rest) f.appendChild(document.createTextNode(rest));
                        n.parentNode.replaceChild(f, n);
                    }
                });
            },

            toggleNode(el) {
                el.closest('li').classList.toggle('tree-collapsed');
            },

            boot() {
                this.waitForSortable(() => this.initAll());

                let pending = false;
                Livewire.hook('morph.updated', () => {
                    if (pending) return;
                    pending = true;
                    requestAnimationFrame(() => {
                        this.initAll();
                        this.reapplyFilters();
                        pending = false;
                    });
                });
            },

            reapplyFilters() {
                this.$root.querySelectorAll('input[type="search"][data-filter-target]').forEach(input => {
                    if (input.value.trim()) this.filterTree(input, input.dataset.filterTarget);
                });
            },

            waitForSortable(cb) {
                if (typeof Sortable !== 'undefined') { cb(); return; }
                setTimeout(() => this.waitForSortable(cb), 150);
            },

            initAll() {
                this.$root.querySelectorAll('[data-sortable]').forEach((el) => {
                    if (!this.sortableMap.has(el)) {
                        this.sortableMap.set(el, this.makeSortable(el));
                    }
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
