/**
 * Alpine directives for the pko/page-builder drag&drop editor.
 *
 * Three directives registered :
 *   - x-pb-palette      : source palette (clone + no sort)
 *   - x-pb-drop         : target drop zone
 *                         data-drop-type = "sections" | "blocks"
 *                         data-section-index (required for blocks)
 *                         data-column-index  (required for blocks)
 *   - x-pb-sortable     : in-place reorder (used for section reorder by handle)
 *                         expression = Livewire method name (default reorderSections)
 *
 * SortableJS is lazy-loaded from the CDN; a single flag guards duplicate init
 * when several Pko packages are mounted on the same page.
 */
(function () {
    if (window.__pkoPageBuilderSortableInstalled) return;
    window.__pkoPageBuilderSortableInstalled = true;

    const SORTABLE_SRC = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
    const GROUP_NAME = 'pko-page-builder';

    function loadSortable() {
        if (window.Sortable) return Promise.resolve(window.Sortable);
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = SORTABLE_SRC;
            script.onload = () => resolve(window.Sortable);
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    function findLivewireComponent(el) {
        const wireEl = el.closest('[wire\\:id]');
        if (!wireEl || !window.Livewire) return null;
        return window.Livewire.find(wireEl.getAttribute('wire:id'));
    }

    function registerDirectives() {
        if (!window.Alpine || window.__pkoPageBuilderDirectivesRegistered) return;
        window.__pkoPageBuilderDirectivesRegistered = true;

        // Palette : source of clonable draggables
        window.Alpine.directive('pb-palette', (el) => {
            loadSortable().then((Sortable) => {
                new Sortable(el, {
                    group: { name: GROUP_NAME, pull: 'clone', put: false },
                    sort: false,
                    draggable: '[data-palette-type]',
                });
            });
        });

        // Drop zones : accept palette items
        window.Alpine.directive('pb-drop', (el) => {
            loadSortable().then((Sortable) => {
                new Sortable(el, {
                    group: { name: GROUP_NAME, pull: false, put: true },
                    sort: false,
                    animation: 150,
                    ghostClass: 'opacity-40',
                    onAdd: (evt) => {
                        const paletteType = evt.item?.dataset?.paletteType;
                        const dropType = el.dataset.dropType;
                        // Remove the cloned DOM element — Livewire re-render will rebuild it.
                        evt.item.remove();
                        if (!paletteType) return;

                        const component = findLivewireComponent(el);
                        if (!component) return;

                        if (dropType === 'sections') {
                            component.call('insertSection', evt.newIndex, paletteType);
                        } else if (dropType === 'blocks') {
                            const sectionIndex = parseInt(el.dataset.sectionIndex, 10);
                            const columnIndex = parseInt(el.dataset.columnIndex, 10);
                            if (!Number.isFinite(sectionIndex) || !Number.isFinite(columnIndex)) return;
                            component.call('insertBlock', sectionIndex, columnIndex, evt.newIndex, paletteType);
                        }
                    },
                });
            });
        });

        // In-place reorder (handle-based)
        window.Alpine.directive('pb-sortable', (el, { expression }) => {
            const method = expression || 'reorderSections';
            const handle = el.dataset.handle || null;
            loadSortable().then((Sortable) => {
                new Sortable(el, {
                    animation: 150,
                    handle: handle,
                    draggable: '[data-id]',
                    ghostClass: 'opacity-40',
                    onEnd: () => {
                        const ids = Array.from(el.querySelectorAll('[data-id]')).map((n) => n.dataset.id);
                        const component = findLivewireComponent(el);
                        if (component) component.call(method, ids);
                    },
                });
            });
        });
    }

    if (window.Alpine) {
        registerDirectives();
    } else {
        document.addEventListener('alpine:init', registerDirectives);
    }
})();
