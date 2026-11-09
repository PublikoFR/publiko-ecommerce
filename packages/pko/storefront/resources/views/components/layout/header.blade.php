@php
use Pko\Storefront\Support\StorefrontLinks;
use Pko\Storefront\Support\StorefrontText;

$nav = StorefrontLinks::visible(config('storefront.nav.secondary', []));
$quoteUrl = StorefrontLinks::safeHref(config('storefront.nav.quote_url', '/contact'));
$topbarLinks = StorefrontLinks::visible(config('storefront.topbar.links', []));
$tagline = brand_tagline();
// Info franco (banner.*) : affichée dans le bandeau au-dessus du menu. Le texte
// accepte les variables dynamiques ({{port_franco}}…) résolues depuis la source
// de vérité unique (cf. StorefrontText).
$franco = config('storefront.banner.enabled') ? StorefrontText::render(config('storefront.banner.text')) : '';
$francoIcon = config('storefront.banner.icon') ?: 'truck';
// Bandeau d'information sous le menu : liste éditable (icône + texte).
$accessItems = collect(config('storefront.access_band.items', []))
    ->filter(fn ($item) => is_array($item) && trim((string) ($item['text'] ?? '')) !== '')
    ->values();

$user = auth()->user();
@endphp

<x-storefront.maintenance-banner />

<header class="sticky top-0 z-40">
    {{-- Bandeau supérieur (forest) : accroche + franco à gauche, liens utilitaires à droite --}}
    @if ($tagline || $franco || $topbarLinks)
        <div class="hidden md:block bg-primary-600 text-white text-[13px]">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-6 h-9">
                <div class="flex items-center gap-3 min-w-0 whitespace-nowrap">
                    @if ($tagline)
                        <span class="font-medium truncate">{{ $tagline }}</span>
                    @endif
                    @if ($tagline && $franco)
                        <span class="text-white/40" aria-hidden="true">·</span>
                    @endif
                    @if ($franco)
                        <span class="flex items-center gap-1.5 text-white/90">
                            <x-ui.icon :name="$francoIcon" class="w-4 h-4 text-accent-400 shrink-0" />
                            {{ $franco }}
                        </span>
                    @endif
                </div>
                @if ($topbarLinks)
                    <nav class="flex items-center gap-3 whitespace-nowrap" aria-label="Liens utiles">
                        @foreach ($topbarLinks as $link)
                            @if (! $loop->first)<span class="text-white/40" aria-hidden="true">·</span>@endif
                            <a href="{{ $link['href'] }}" class="hover:text-accent-300 transition" @if (StorefrontLinks::isInternal($link['href'])) wire:navigate @endif>{{ $link['label'] }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>
    @endif

    {{-- Main bar --}}
    <div class="bg-white border-b border-neutral-200">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-2 sm:gap-4 lg:gap-7 h-[78px]">
                {{-- Mobile burger --}}
                <button type="button" class="lg:hidden shrink-0 text-neutral-700" x-data @click="$dispatch('open-lateral-menu')" aria-label="Menu">
                    <x-ui.icon name="menu" class="w-6 h-6" />
                </button>

                {{-- Logo --}}
                {{-- Le logo de repli est un SVG 220x44 : à h-14 il fait 280px de large et
                     pousse le bloc actions hors du viewport en mobile (scroll horizontal).
                     On borne donc sa largeur tant qu'on n'est pas en desktop. --}}
                <a href="/" class="flex items-center shrink-0 min-w-0" wire:navigate>
                    <span class="sr-only">{{ brand_name() }}</span>
                    <x-layout.logo class="h-9 w-auto max-w-[34vw] object-contain sm:h-11 sm:max-w-[200px] lg:h-14 lg:max-w-none" />
                </a>

                {{-- Search --}}
                <div class="hidden md:flex flex-1 min-w-0">
                    @livewire('storefront.search-autocomplete', [], key('search-desktop'))
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1 lg:gap-1.5 ml-auto shrink-0">
                    @if ($user)
                        <x-ui.dropdown align="right" width="w-64">
                            <x-slot:trigger>
                                <button type="button" class="flex flex-col items-center gap-0.5 px-1.5 sm:px-2.5 py-1.5 rounded-md text-primary-600 hover:bg-primary-50 transition">
                                    <x-ui.icon name="user" class="w-[22px] h-[22px]" />
                                    <span class="text-[11px] font-medium text-neutral-600 max-w-[5rem] sm:max-w-[8rem] truncate">{{ $user->name ?? $user->email }}</span>
                                </button>
                            </x-slot:trigger>
                            <x-ui.dropdown-item href="/compte" icon="user">Mon tableau de bord</x-ui.dropdown-item>
                            <x-ui.dropdown-item href="/compte/commandes" icon="shopping-bag">Mes commandes</x-ui.dropdown-item>
                            <x-ui.dropdown-item href="/compte/listes-achat" icon="list">Mes listes d'achat</x-ui.dropdown-item>
                            <x-ui.dropdown-item href="/compte/fidelite" icon="check">Mon programme fidélité</x-ui.dropdown-item>
                            <div class="border-t border-neutral-100 my-1"></div>
                            <form method="POST" action="/deconnexion">@csrf
                                <x-ui.dropdown-item icon="logout" type="submit">Se déconnecter</x-ui.dropdown-item>
                            </form>
                        </x-ui.dropdown>
                    @else
                        <a href="/connexion" class="flex flex-col items-center gap-0.5 px-1.5 sm:px-2.5 py-1.5 rounded-md text-primary-600 hover:bg-primary-50 transition">
                            <x-ui.icon name="user" class="w-[22px] h-[22px]" />
                            <span class="text-[11px] font-medium text-neutral-600">Compte pro</span>
                        </a>
                    @endif

                    <a href="/compte/listes-achat" class="hidden lg:flex flex-col items-center gap-0.5 px-2.5 py-1.5 rounded-md text-primary-600 hover:bg-primary-50 transition">
                        <x-ui.icon name="list" class="w-[22px] h-[22px]" />
                        <span class="text-[11px] font-medium text-neutral-600">Mes listes d'achat</span>
                    </a>

                    @auth
                        @livewire('storefront.cart-badge')
                    @else
                        <a href="/connexion" class="relative flex flex-col items-center gap-0.5 px-1.5 sm:px-2.5 py-1.5 rounded-md text-primary-600 hover:bg-primary-50 transition">
                            <x-ui.icon name="cart" class="w-[22px] h-[22px]" />
                            <span class="text-[11px] font-medium text-neutral-600">Panier</span>
                        </a>
                    @endauth
                </div>
            </div>

            {{-- Mobile search (row 2) — composant Livewire pour l'autocomplétion ajax
                 (identique au desktop). L'ancienne search-bar statique (form GET) ne
                 déclenchait pas la recherche ajax en mobile. --}}
            <div class="md:hidden pb-3">
                @livewire('storefront.search-autocomplete', [], key('search-mobile'))
            </div>

            {{-- Menu principal (blanc, soulignement lime au survol) --}}
            <nav class="hidden lg:flex items-center gap-1 h-14 -mb-px overflow-x-auto no-scrollbar" aria-label="Navigation principale">
                <button
                    type="button"
                    x-data
                    @click="$dispatch('open-lateral-menu')"
                    class="flex items-center gap-2 h-full px-3 xl:px-4 font-semibold text-sm text-primary-600 border-b-[3px] border-transparent hover:border-accent-500 transition shrink-0"
                >
                    <x-ui.icon name="menu" class="w-[18px] h-[18px]" />
                    <span>Tous nos produits</span>
                </button>

                @foreach ($nav as $item)
                    <a
                        href="{{ $item['href'] }}"
                        @if (StorefrontLinks::isInternal($item['href'])) wire:navigate @endif
                        class="flex items-center gap-2 h-full px-3 xl:px-4 font-medium text-sm text-neutral-700 border-b-[3px] border-transparent hover:text-primary-600 hover:border-accent-500 transition shrink-0"
                    >
                        @if (! empty($item['icon']))<x-ui.icon :name="$item['icon']" class="w-[18px] h-[18px]" />@endif
                        {{ $item['label'] }}
                    </a>
                @endforeach

                @if ($quoteUrl)
                    <x-ui.button variant="primary" size="sm" :href="$quoteUrl" iconRight="arrow-up-right" class="ml-auto shrink-0">
                        Demander un devis
                    </x-ui.button>
                @endif
            </nav>
        </div>
    </div>

    {{-- Bandeau d'information sous le menu (liste éditable en admin) --}}
    @if ($accessItems->isNotEmpty())
        <div class="bg-neutral-100 border-b border-neutral-200 text-neutral-600 text-[13px]">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap items-center justify-center gap-x-5 gap-y-1">
                @foreach ($accessItems as $item)
                    @if (! $loop->first)<span class="hidden sm:inline text-neutral-300" aria-hidden="true">|</span>@endif
                    <span class="flex items-center gap-2">
                        <x-ui.icon :name="$item['icon'] ?? 'check'" class="w-4 h-4 text-primary-600 shrink-0" />
                        {{ StorefrontText::render($item['text']) }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</header>
