@php
use Illuminate\Support\Facades\Cache;
use Lunar\Models\Collection;
use Pko\Storefront\StorefrontServiceProvider;

$lateralCollections = Cache::remember(StorefrontServiceProvider::NAV_CACHE_KEY, 3600, function () {
    return Collection::with([
        'defaultUrl',
        'media',
        'children' => fn ($q) => $q
            ->where('pko_enabled', true)
            ->with([
                'defaultUrl',
                'children' => fn ($q2) => $q2
                    ->where('pko_enabled', true)
                    ->with('defaultUrl')
                    ->orderBy('_lft'),
            ])
            ->orderBy('_lft'),
    ])
    ->whereIsRoot()
    ->where('pko_enabled', true)
    ->orderBy('_lft')
    ->get();
});
@endphp

<div
    x-data="{
        open: false,
        l1: null,
        l2: null,
        // Le chevron L1 est partagé mobile/desktop : sur mobile il déplie un
        // accordéon (rotation = bonne affordance), sur desktop il ouvre une
        // colonne latérale (rotation trompeuse). Réactif au redimensionnement.
        isDesktop: window.innerWidth >= 1024,
        openMenu() {
            this.open = true;
            this.l1 = null;
            this.l2 = null;
            document.body.classList.add('overflow-hidden');
        },
        closeMenu() {
            this.open = false;
            this.l1 = null;
            this.l2 = null;
            document.body.classList.remove('overflow-hidden');
        },
    }"
    @resize.window="isDesktop = window.innerWidth >= 1024"
    @open-modal-mobile-nav.window="openMenu()"
    @open-lateral-menu.window="openMenu()"
    @keydown.escape.window="if (open) closeMenu()"
>
    {{-- Overlay --}}
    <div
        x-show="open"
        x-transition:enter="transition-opacity duration-300 ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-300 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-primary-900/40 backdrop-blur-sm z-[60]"
        @click="closeMenu()"
        aria-hidden="true"
        style="display: none;"
    ></div>

    {{-- Off-canvas container --}}
    <div
        x-show="open"
        x-transition:enter="transition-transform duration-300 ease-out"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform duration-300 ease-in"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 z-[61] flex shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-label="Menu des catégories"
        style="display: none;"
    >
        {{-- Panel L1 --}}
        <nav
            class="w-screen max-w-xs lg:w-72 lg:max-w-none bg-white flex flex-col h-full overflow-hidden"
            aria-label="Catégories niveau 1"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3.5 bg-primary-600 text-white shrink-0">
                <span class="font-display font-bold text-base">Tous nos produits</span>
                <button
                    type="button"
                    @click="closeMenu()"
                    class="p-1.5 rounded-md hover:bg-primary-700 transition"
                    aria-label="Fermer le menu"
                >
                    <x-ui.icon name="close" class="w-5 h-5" />
                </button>
            </div>

            {{-- Liste L1 --}}
            <ul class="flex-1 overflow-y-auto divide-y divide-neutral-100" role="menu">
                @forelse ($lateralCollections as $col)
                    @php
                        $colUrl = $col->defaultUrl?->slug ? route('collection.view', $col->defaultUrl->slug) : '#';
                        // Une catégorie « page de listing de catégories » se
                        // parcourt en descendant les pages, pas via un sous-menu :
                        // on la rend comme un lien simple (ni chevron, ni survol).
                        $colHasChildren = $col->children->isNotEmpty() && ! $col->pko_browse_children;
                        // pko_media_url : conversion « small » si elle existe, sinon
                        // l'original. Un picto SVG n'a pas de conversion raster et
                        // getFirstMediaUrl('images', 'small') renvoyait une URL morte.
                        $colImg = pko_media_url($col->getFirstMedia('images'), 'small');
                    @endphp
                    <li role="none">
                        <div
                            class="group flex items-center gap-3 px-3 py-2.5 transition-colors"
                            :class="{ 'bg-primary-50': l1 === {{ $col->id }} }"
                            @mouseenter="if (window.innerWidth >= 1024) { l1 = {{ $colHasChildren ? $col->id : 'null' }}; l2 = null }"
                        >
                            {{-- Vignette : tuile transparente au repos, vert clair au survol de
                                 la ligne ou quand la catégorie est ouverte. Avec un picto bicolore
                                 de la charte (accent vert clair), l'accent se fond dans la tuile :
                                 le rendu est exactement la déclinaison « sur vert clair » du
                                 graphiste, sans second fichier à gérer (cf. docs/packages/storefront.md).
                                 Fond neutre conservé uniquement pour l'icône de repli. --}}
                            <span
                                class="shrink-0 w-10 h-10 rounded-md overflow-hidden flex items-center justify-center transition-colors {{ $colImg ? 'p-1 group-hover:bg-accent-500' : 'bg-neutral-100' }}"
                                @if ($colImg) :class="{ 'bg-accent-500': l1 === {{ $col->id }} }" @endif
                            >
                                @if ($colImg)
                                    <img
                                        src="{{ $colImg }}"
                                        alt=""
                                        width="32"
                                        height="32"
                                        class="w-full h-full object-contain"
                                        loading="lazy"
                                    >
                                @else
                                    <x-ui.icon name="shopping-bag" class="w-4 h-4 text-neutral-300" />
                                @endif
                            </span>

                            {{-- Nom (lien de navigation) --}}
                            <a
                                href="{{ $colUrl }}"
                                class="flex-1 text-sm font-semibold text-neutral-800 hover:text-primary-700 transition py-1"
                                :class="{ 'text-primary-700': l1 === {{ $col->id }} }"
                                role="menuitem"
                            >
                                {{ $col->translateAttribute('name') }}
                            </a>

                            {{-- Chevron si enfants --}}
                            @if ($colHasChildren)
                                <button
                                    type="button"
                                    @click.stop="l1 = (l1 === {{ $col->id }} ? null : {{ $col->id }}); l2 = null"
                                    class="shrink-0 p-1 rounded hover:bg-primary-100 transition"
                                    :aria-expanded="(l1 === {{ $col->id }}).toString()"
                                    aria-label="Sous-catégories de {{ $col->translateAttribute('name') }}"
                                >
                                    <x-ui.icon
                                        name="chevron-right"
                                        class="w-4 h-4 text-neutral-400 transition-transform duration-200"
                                        x-bind:class="{
                                            'rotate-90': l1 === {{ $col->id }} && ! isDesktop,
                                            'text-primary-500': l1 === {{ $col->id }},
                                        }"
                                    />
                                </button>
                            @endif
                        </div>

                        {{-- Accordéon mobile L2 (masqué sur lg+) --}}
                        @if ($colHasChildren)
                            <div
                                x-show="l1 === {{ $col->id }}"
                                class="lg:hidden bg-neutral-50 border-t border-neutral-100"
                                style="display: none;"
                            >
                                <ul class="py-1" role="menu">
                                    @foreach ($col->children as $child)
                                        <li role="none">
                                            <a
                                                href="{{ $child->defaultUrl?->slug ? route('collection.view', $child->defaultUrl->slug) : '#' }}"
                                                class="block px-6 py-2 text-sm text-neutral-600 hover:text-primary-600 hover:bg-neutral-100 transition"
                                                role="menuitem"
                                            >
                                                {{ $child->translateAttribute('name') }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-8 text-sm text-neutral-400 text-center">
                        Catalogue en cours de chargement…
                    </li>
                @endforelse
            </ul>

            {{-- Liens du menu principal : sur desktop ils sont dans la barre de
                 navigation du header, masquée sous lg → on les reprend ici. --}}
            @php
                $mobileNav = \Pko\Storefront\Support\StorefrontLinks::visible(config('storefront.nav.secondary', []));
                $mobileQuoteUrl = \Pko\Storefront\Support\StorefrontLinks::safeHref(config('storefront.nav.quote_url', '/contact'));
            @endphp
            @if ($mobileNav || $mobileQuoteUrl)
                <div class="lg:hidden shrink-0 border-t border-neutral-200 bg-neutral-50 px-4 py-3 space-y-1">
                    @foreach ($mobileNav as $link)
                        <a href="{{ $link['href'] }}" class="block py-1.5 text-sm font-medium text-neutral-700 hover:text-primary-600 transition">{{ $link['label'] }}</a>
                    @endforeach
                    @if ($mobileQuoteUrl)
                        <x-ui.button variant="primary" size="sm" :href="$mobileQuoteUrl" iconRight="arrow-up-right" class="mt-2 w-full">Demander un devis</x-ui.button>
                    @endif
                </div>
            @endif
        </nav>

        {{-- Panel L2 — desktop uniquement (colonne masquée si aucune L1 active) --}}
        <div class="hidden lg:block bg-neutral-50 border-neutral-200 h-full overflow-hidden transition-all duration-200" :class="l1 !== null ? 'lg:w-64 border-l' : 'lg:w-0'">
            <div x-show="l1 !== null" class="h-full flex flex-col overflow-hidden" style="display: none;">
                <div class="flex-1 overflow-y-auto">
                    @foreach ($lateralCollections as $col)
                        @if ($col->children->isNotEmpty())
                            <div x-show="l1 === {{ $col->id }}" style="display: none;">
                                {{-- Titre de la catégorie L1 --}}
                                <div class="px-4 py-3 border-b border-neutral-200 sticky top-0 bg-neutral-50 z-10">
                                    <a
                                        href="{{ $col->defaultUrl?->slug ? route('collection.view', $col->defaultUrl->slug) : '#' }}"
                                        class="font-bold text-sm text-primary-700 hover:text-primary-900 transition"
                                    >
                                        {{ $col->translateAttribute('name') }}
                                    </a>
                                </div>
                                <ul class="py-1" role="menu" aria-label="Catégories niveau 2">
                                    @foreach ($col->children as $child)
                                        @php $childHasChildren = $child->children->isNotEmpty(); @endphp
                                        <li role="none">
                                            <div
                                                class="flex items-center px-4 py-2.5 hover:bg-white transition"
                                                :class="{ 'bg-white': l2 === {{ $child->id }} }"
                                                @mouseenter="l2 = {{ $childHasChildren ? $child->id : 'null' }}"
                                            >
                                                <a
                                                    href="{{ $child->defaultUrl?->slug ? route('collection.view', $child->defaultUrl->slug) : '#' }}"
                                                    class="flex-1 text-sm text-neutral-700 hover:text-primary-600 transition"
                                                    :class="{ 'text-primary-600 font-semibold': l2 === {{ $child->id }} }"
                                                    role="menuitem"
                                                >
                                                    {{ $child->translateAttribute('name') }}
                                                </a>
                                                @if ($childHasChildren)
                                                    <button
                                                        type="button"
                                                        @click="l2 = (l2 === {{ $child->id }} ? null : {{ $child->id }})"
                                                        class="shrink-0 p-1 rounded hover:bg-primary-100 transition"
                                                        :aria-expanded="(l2 === {{ $child->id }}).toString()"
                                                        aria-label="Sous-catégories de {{ $child->translateAttribute('name') }}"
                                                    >
                                                        <x-ui.icon
                                                            name="chevron-right"
                                                            {{-- Pas de rotation : ce panneau est desktop-only et
                                                                 ouvre une colonne latérale, pas un dépliant. La
                                                                 flèche doit continuer de pointer vers la droite. --}}
                                                            class="w-3.5 h-3.5 text-neutral-400 shrink-0 transition-colors duration-200"
                                                            x-bind:class="{ 'text-primary-500': l2 === {{ $child->id }} }"
                                                        />
                                                    </button>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Panel L3 — desktop uniquement (colonne masquée si aucune L2 active) --}}
        <div class="hidden lg:block bg-white border-neutral-200 h-full overflow-hidden transition-all duration-200" :class="l2 !== null ? 'lg:w-56 border-l' : 'lg:w-0'">
            <div x-show="l2 !== null" class="h-full flex flex-col overflow-hidden" style="display: none;">
                <div class="flex-1 overflow-y-auto">
                    @foreach ($lateralCollections as $col)
                        @foreach ($col->children as $child)
                            @if ($child->children->isNotEmpty())
                                <div x-show="l2 === {{ $child->id }}" style="display: none;">
                                    {{-- Titre de la catégorie L2 --}}
                                    <div class="px-4 py-3 border-b border-neutral-200 sticky top-0 bg-white z-10">
                                        <a
                                            href="{{ $child->defaultUrl?->slug ? route('collection.view', $child->defaultUrl->slug) : '#' }}"
                                            class="font-bold text-sm text-primary-700 hover:text-primary-900 transition"
                                        >
                                            {{ $child->translateAttribute('name') }}
                                        </a>
                                    </div>
                                    <ul class="py-1" role="menu" aria-label="Catégories niveau 3">
                                        @foreach ($child->children as $grand)
                                            <li role="none">
                                                <a
                                                    href="{{ $grand->defaultUrl?->slug ? route('collection.view', $grand->defaultUrl->slug) : '#' }}"
                                                    class="block px-4 py-2.5 text-sm text-neutral-600 hover:text-primary-600 hover:bg-neutral-50 transition"
                                                    role="menuitem"
                                                >
                                                    {{ $grand->translateAttribute('name') }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
