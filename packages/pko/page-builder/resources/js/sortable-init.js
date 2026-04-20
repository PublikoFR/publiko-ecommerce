/**
 * Alpine directive x-sortable for the pko/page-builder editor. Shares the
 * same SortableJS CDN as other Pko packages (they guard with a global flag,
 * so loading is deduplicated at runtime). Re-registered here so the directive
 * is usable even if only this package is loaded.
 */
(function () {
    if (window.__pkoPageBuilderSortableInstalled) return;
    window.__pkoPageBuilderSortableInstalled = true;

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

    function registerDirective() {
        if (!window.Alpine || window.__pkoPageBuilderDirectiveRegistered) return;
        window.__pkoPageBuilderDirectiveRegistered = true;
        window.Alpine.directive('sortable', (el, { expression }) => {
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
        registerDirective();
    } else {
        document.addEventListener('alpine:init', registerDirective);
    }
})();
