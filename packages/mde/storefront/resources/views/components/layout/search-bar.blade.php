@props([])

<form method="GET" action="/recherche" class="flex w-full" role="search">
    <div class="relative flex-1">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-neutral-400">
            <x-ui.icon name="search" class="w-5 h-5" />
        </div>
        <input
            type="search"
            name="q"
            value="{{ request('q') }}"
            placeholder="Rechercher un article, une marque, une référence…"
            class="block w-full pl-10 pr-4 py-2.5 rounded-l-md border-r-0 border-neutral-300 focus:border-primary-500 focus:ring-primary-500 text-sm placeholder:text-neutral-400"
            aria-label="Rechercher"
            autocomplete="off"
        />
    </div>
    <button type="submit" class="inline-flex items-center justify-center bg-primary-600 hover:bg-primary-700 text-white px-5 rounded-r-md font-semibold text-sm transition">
        <span class="hidden sm:inline">Rechercher</span>
        <x-ui.icon name="search" class="w-5 h-5 sm:hidden" />
    </button>
</form>
