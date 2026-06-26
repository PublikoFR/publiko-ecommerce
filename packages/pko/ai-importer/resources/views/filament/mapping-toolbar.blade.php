{{--
    Barre d'outils du blueprint de mapping : recherche + « masquer les colonnes
    vides ». Filtrage 100 % client-side (Alpine) sur les items du repeater frère
    (`[data-mapping-repeater]`) — aucun aller-retour Livewire. Dégradation
    gracieuse : si le repeater n'est pas trouvé, la barre ne fait rien et le
    formulaire reste pleinement fonctionnel.
--}}
<div
    x-data="{
        search: '',
        hideEmpty: false,
        repeater: null,
        observer: null,
        init() {
            this.repeater = document.querySelector('[data-mapping-repeater]');
            if (this.repeater) {
                this.observer = new MutationObserver(() => this.apply());
                this.observer.observe(this.repeater, { childList: true, subtree: true });
            }
            this.$nextTick(() => this.apply());
        },
        destroy() {
            this.observer?.disconnect();
        },
        apply() {
            if (! this.repeater) return;
            const q = this.search.trim().toLowerCase();
            this.repeater.querySelectorAll('.fi-fo-repeater-item').forEach((item) => {
                const text = item.textContent.toLowerCase();
                const isEmpty = text.includes('aucune configuration');
                let show = true;
                if (q && ! text.includes(q)) show = false;
                if (this.hideEmpty && isEmpty) show = false;
                item.style.display = show ? '' : 'none';
            });
        },
    }"
    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
>
    <div class="relative flex-1">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-5 w-5" />
        </span>
        <input
            type="search"
            x-model="search"
            @input="apply()"
            placeholder="Rechercher une colonne (nom, feuille, action…)"
            class="block w-full rounded-lg border-gray-300 bg-white py-2 pl-10 pr-3 text-sm shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
        />
    </div>

    <label class="inline-flex cursor-pointer select-none items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
        <input
            type="checkbox"
            x-model="hideEmpty"
            @change="apply()"
            class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
        />
        Masquer les colonnes vides
    </label>
</div>
