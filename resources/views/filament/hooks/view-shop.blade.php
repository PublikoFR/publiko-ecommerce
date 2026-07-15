{{-- Lien « Voir la boutique » affiché dans la topbar admin, à droite de la
     recherche globale. Injecté via renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER).
     Ouvre le front (route storefront) dans un nouvel onglet. --}}
<a
    href="{{ url('/') }}"
    target="_blank"
    rel="noopener"
    class="hidden md:inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-white/5"
>
    {{ svg('heroicon-o-shopping-bag', 'w-5 h-5') }}
    Voir la boutique
</a>
