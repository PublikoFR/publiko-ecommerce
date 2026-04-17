@php
use Illuminate\Support\Facades\Cache;
use Lunar\Facades\CartSession;
use Lunar\Models\Collection;

$contact = config('mde-storefront.contact');
$nav = config('mde-storefront.nav.secondary', []);

$rootCollections = Cache::remember('mde.storefront.nav.roots.v1', 3600, function () {
    return Collection::with(['defaultUrl', 'children' => fn ($q) => $q->with('defaultUrl')])
        ->whereIsRoot()
        ->orderBy('_lft')
        ->get();
});

try {
    $cart = CartSession::current();
    $cartCount = (int) ($cart?->lines()->count() ?? 0);
} catch (\Throwable) {
    $cartCount = 0;
}

$user = auth()->user();
@endphp

<header class="bg-white border-b border-neutral-200 sticky top-0 z-40">
    {{-- Top contact bar --}}
    <div class="hidden md:block bg-primary-900 text-primary-50 text-sm">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between py-1.5">
            <div class="flex items-center gap-4">
                <a href="tel:{{ preg_replace('/\s/', '', $contact['phone']) }}" class="flex items-center gap-1.5 hover:text-white transition">
                    <x-ui.icon name="phone" class="w-4 h-4" />
                    <span class="font-medium">{{ $contact['tagline'] }}</span>
                    <span class="font-bold">{{ $contact['phone'] }}</span>
                </a>
            </div>
            <div class="flex items-center gap-5 text-xs">
                <a href="/magasins" class="hover:text-white transition flex items-center gap-1">
                    <x-ui.icon name="map-pin" class="w-3.5 h-3.5" /> Nos magasins
                </a>
                <a href="/actualites" class="hover:text-white transition">Actualités</a>
                <a href="/pages/nous-contacter" class="hover:text-white transition">Contact</a>
            </div>
        </div>
    </div>

    {{-- Main bar --}}
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4 lg:gap-8 py-4">
            {{-- Mobile burger --}}
            <button type="button" class="lg:hidden text-neutral-700" x-data @click="$dispatch('open-modal-mobile-nav')" aria-label="Menu">
                <x-ui.icon name="menu" class="w-6 h-6" />
            </button>

            {{-- Logo --}}
            <a href="/" class="flex items-center shrink-0" wire:navigate>
                <span class="sr-only">MDE Distribution</span>
                <x-layout.logo class="h-8 w-auto" />
            </a>

            {{-- Search --}}
            <div class="hidden md:flex flex-1 max-w-2xl">
                @livewire('storefront.search-autocomplete')
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-1 lg:gap-2 ml-auto">
                <a href="/achat-rapide" class="hidden md:flex flex-col items-center gap-0.5 px-3 py-1.5 rounded-md text-neutral-700 hover:text-primary-700 hover:bg-primary-50 transition">
                    <x-ui.icon name="lightning" class="w-5 h-5" />
                    <span class="text-[11px] font-semibold uppercase tracking-wide">Achat rapide</span>
                </a>

                @if ($user)
                    <x-ui.dropdown align="right" width="w-64">
                        <x-slot:trigger>
                            <button type="button" class="flex flex-col items-center gap-0.5 px-3 py-1.5 rounded-md text-neutral-700 hover:text-primary-700 hover:bg-primary-50 transition">
                                <x-ui.icon name="user" class="w-5 h-5" />
                                <span class="text-[11px] font-semibold uppercase tracking-wide max-w-[8rem] truncate">{{ $user->name ?? $user->email }}</span>
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
                    <a href="/connexion" class="flex flex-col items-center gap-0.5 px-3 py-1.5 rounded-md text-neutral-700 hover:text-primary-700 hover:bg-primary-50 transition">
                        <x-ui.icon name="user" class="w-5 h-5" />
                        <span class="text-[11px] font-semibold uppercase tracking-wide">Se connecter</span>
                    </a>
                @endif

                <a href="/panier" class="relative flex flex-col items-center gap-0.5 px-3 py-1.5 rounded-md text-neutral-700 hover:text-primary-700 hover:bg-primary-50 transition">
                    <div class="relative">
                        <x-ui.icon name="cart" class="w-5 h-5" />
                        @if ($cartCount > 0)
                            <span class="absolute -top-1.5 -right-2 bg-primary-600 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">{{ $cartCount }}</span>
                        @endif
                    </div>
                    <span class="text-[11px] font-semibold uppercase tracking-wide">Panier</span>
                </a>
            </div>
        </div>

        {{-- Mobile search (row 2) --}}
        <div class="md:hidden pb-3">
            <x-layout.search-bar />
        </div>
    </div>

    {{-- Secondary nav --}}
    <nav class="bg-primary-700 text-white">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center overflow-x-auto no-scrollbar">
            <div x-data="{ open: false }" class="relative shrink-0" @click.away="open = false">
                <button type="button" @click="open = !open" class="flex items-center gap-2 py-3 pr-4 font-semibold text-sm uppercase tracking-wide hover:bg-primary-800 transition">
                    <x-ui.icon name="menu" class="w-5 h-5" />
                    <span>Tous nos produits</span>
                    <x-ui.icon name="chevron-down" class="w-4 h-4" />
                </button>
                <div x-show="open" x-transition style="display: none;" class="absolute left-0 top-full z-50 w-screen max-w-4xl bg-white shadow-2xl border border-neutral-200 rounded-b-lg text-neutral-800">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-1 p-4 max-h-[70vh] overflow-y-auto">
                        @forelse ($rootCollections as $root)
                            <div class="p-3">
                                <a href="{{ $root->defaultUrl?->slug ? route('collection.view', $root->defaultUrl->slug) : '#' }}" class="block font-bold text-primary-700 hover:text-primary-900 mb-2">
                                    {{ $root->translateAttribute('name') }}
                                </a>
                                @if ($root->children->isNotEmpty())
                                    <ul class="space-y-1">
                                        @foreach ($root->children->take(8) as $child)
                                            <li>
                                                <a href="{{ $child->defaultUrl?->slug ? route('collection.view', $child->defaultUrl->slug) : '#' }}" class="text-sm text-neutral-600 hover:text-primary-600 transition">
                                                    {{ $child->translateAttribute('name') }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @empty
                            <p class="p-4 text-sm text-neutral-500 col-span-full">Catalogue en cours de chargement…</p>
                        @endforelse
                    </div>
                </div>
            </div>

            @foreach ($nav as $item)
                <a href="{{ $item['href'] ?? '#' }}" class="shrink-0 py-3 px-4 font-semibold text-sm uppercase tracking-wide hover:bg-primary-800 transition">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </nav>

    {{-- Info banner --}}
    @if (config('mde-storefront.banner.enabled'))
        <div class="bg-amber-50 border-b border-amber-100 text-amber-900 text-sm">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-center gap-2 font-medium">
                <x-ui.icon name="{{ config('mde-storefront.banner.icon', 'truck') }}" class="w-4 h-4" />
                <span>{{ config('mde-storefront.banner.text') }}</span>
            </div>
        </div>
    @endif
</header>
