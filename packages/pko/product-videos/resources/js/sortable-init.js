/**
 * Alpine + SortableJS directive for drag&drop reordering of product videos
 * in the Filament admin. Loads SortableJS from a CDN on first use so we don't
 * force a full bundler setup on the package.
 *
 * Usage (Blade) :
 *
 *   <div x-data x-sortable="reorderVideos" data-handle=".pko-video-handle">
 *     <div data-id="{{ $video->id }}">…</div>
 *     …
 *   </div>
 *
 * On drop, fires `reorderVideos([id1, id2, …])` on the enclosing Livewire
 * component. The attribute value (`reorderVideos`) names the Livewire method
 * to call.
 */
(function () {
    if (window.__pkoProductVideosSortableInstalled) return;
    window.__pkoProductVideosSortableInstalled = true;

    const SORTABLE_SRC = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';

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

    function init() {
        if (!window.Alpine) return;
        window.Alpine.directive('sortable', (el, { expression }, { evaluate }) => {
            const method = expression || 'reorderVideos';
            const handle = el.dataset.handle || null;

            loadSortable().then((Sortable) => {
                new Sortable(el, {
                    animation: 150,
                    handle: handle,
                    draggable: '[data-id]',
                    ghostClass: 'opacity-40',
                    onEnd: () => {
                        const ids = Array.from(el.querySelectorAll('[data-id]'))
                            .map((n) => parseInt(n.dataset.id, 10))
                            .filter((n) => Number.isFinite(n) && n > 0);
                        const wireEl = el.closest('[wire\\:id]');
                        if (!wireEl || !window.Livewire) return;
                        const wireId = wireEl.getAttribute('wire:id');
                        const component = window.Livewire.find(wireId);
                        if (component) component.call(method, ids);
                    },
                });
            });
        });
    }

    if (window.Alpine) {
        init();
    } else {
        document.addEventListener('alpine:init', init);
    }
})();
