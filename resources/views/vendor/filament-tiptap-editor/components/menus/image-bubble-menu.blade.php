@props([
    'statePath' => null,
    'tools' => [],
])

{{-- Override Pko : bulle sur image = réglages (alt, max-width responsive) + suppression.
     Le max-width est préféré à width pour préserver le responsive. --}}
<div
    x-data="{
        open: false,
        alt: '',
        maxWidth: '',
        load() {
            const attrs = editor().getAttributes('image') || {};
            this.alt = attrs.alt || '';
            // Tente de récupérer le max-width depuis l'attribut style déjà posé.
            const style = attrs.style || '';
            const m = style.match(/max-width\s*:\s*([^;]+)/i);
            this.maxWidth = m ? m[1].trim() : '';
            this.open = true;
        },
        apply() {
            const value = String(this.maxWidth || '').trim();
            const normalized = value === '' || /(px|%|rem|em)$/i.test(value)
                ? value
                : (value.replace(/[^0-9.]/g, '') + 'px');
            const style = normalized === '' ? null : `max-width: ${normalized}; height: auto;`;
            editor()
                .chain()
                .focus()
                .updateAttributes('image', {
                    alt: this.alt || null,
                    title: this.alt || null,
                    style: style,
                    width: null,
                    height: null,
                })
                .run();
            this.open = false;
        },
    }"
    class="relative flex gap-1 items-center"
    x-show="editor().isActive('image', updatedAt)"
    style="display: none;"
>
    <button
        type="button"
        class="tiptap-tool"
        x-tooltip="'Paramètres de l\'image'"
        x-on:click="load()"
    >
        @svg('heroicon-m-cog-6-tooth', 'w-4 h-4')
        <span class="sr-only">Paramètres</span>
    </button>

    <button
        type="button"
        class="tiptap-tool"
        x-tooltip="'Supprimer l\'image'"
        x-on:click="editor().chain().focus().deleteSelection().run()"
    >
        @svg('heroicon-m-trash', 'w-4 h-4')
        <span class="sr-only">Supprimer</span>
    </button>

    {{-- Popover réglages --}}
    <div
        x-show="open"
        x-cloak
        x-on:click.away="open = false"
        x-transition.opacity
        class="absolute top-full mt-2 left-0 z-50 w-72 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-lg p-3 space-y-2"
    >
        <h4 class="text-xs font-semibold text-gray-900 dark:text-white">Paramètres de l'image</h4>

        <div>
            <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Texte alternatif (alt)</label>
            <input
                type="text"
                x-model="alt"
                placeholder="Décris brièvement l'image…"
                class="w-full text-xs border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-950 text-gray-900 dark:text-white"
            />
        </div>

        <div>
            <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Largeur maximale (responsive)</label>
            <input
                type="text"
                x-model="maxWidth"
                placeholder="ex: 480 ou 80%"
                class="w-full text-xs border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-950 text-gray-900 dark:text-white"
            />
            <p class="text-[10px] text-gray-500 mt-1">Accepte px, %, rem, em. Vide = taille naturelle.</p>
        </div>

        <div class="flex justify-end gap-2 pt-1">
            <button
                type="button"
                x-on:click="open = false"
                class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-white/10 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/5"
            >Annuler</button>
            <button
                type="button"
                x-on:click="apply()"
                class="text-xs px-2 py-1 rounded bg-primary-600 text-white hover:bg-primary-500"
            >Appliquer</button>
        </div>
    </div>
</div>
