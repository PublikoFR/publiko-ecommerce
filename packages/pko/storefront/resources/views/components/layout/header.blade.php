@php
use Lunar\Facades\CartSession;

$contact = config('storefront.contact');
$nav = config('storefront.nav.secondary', []);
$quoteUrl = config('storefront.nav.quote_url', '/contact');
$delivery = config('storefront.banner.text') ?: 'Livraison chantier · Retrait en magasin';

try {
    $cart = CartSession::current();
    $cartCount = (int) ($cart?->lines()->count() ?? 0);
} catch (\Throwable) {
    $cartCount = 0;
}

$user = auth()->user();
@endphp

<header class="sticky top-0 z-40">
    {{-- Utility bar (forest) --}}
    <div class="hidden md:block bg-primary-600 text-white text-sm">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-9">
            <div class="flex items-center gap-2 whitespace-nowrap">
                <x-ui.icon name="truck" class="w-4 h-4 text-accent-400" />
                <span>{{ $delivery }}</span>
            </div>
            <div class="flex items-center gap-5 whitespace-nowrap">
                <a href="tel:{{ preg_replace('/\s/', '', $contact['phone']) }}" class="flex items-center gap-1.5 hover:text-accent-300 transition">
                    <x-ui.icon name="phone" class="w-4 h-4 text-accent-400" />
                    <span class="font-semibold">{{ $contact['phone'] }}</span>
                </a>
                <a href="/magasins" class="flex items-center gap-1.5 hover:text-accent-300 transition">
                    <x-ui.icon name="map-pin" class="w-4 h-4 text-accent-400" /> Nos magasins
                </a>
            </div>
        </div>
    </div>

    {{-- Main bar --}}
    <div class="bg-white border-b border-neutral-200">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4 lg:gap-7 h-[78px]">
                {{-- Mobile burger --}}
                <button type="button" class="lg:hidden text-neutral-700" x-data @click="$dispatch('open-lateral-menu')" aria-label="Menu">
                    <x-ui.icon name="menu" class="w-6 h-6" />
                </button>

                {{-- Logo --}}
                <a href="/" class="flex items-center shrink-0" wire:navigate>
                    <span class="sr-only">{{ brand_name() }}</span>
                    <x-layout.logo class="h-14 w-auto" />
                </a>

                {{-- Search --}}
                <div class="hidden md:flex flex-1 min-w-0">
                    @livewire('storefront.search-autocomplete')
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1 lg:gap-1.5 ml-auto shrink-0">
                    @if ($user)
                        <x-ui.dropdown align="right" width="w-64">
                            <x-slot:trigger>
                                <button type="button" class="flex flex-col items-center gap-0.5 px-2.5 py-1.5 rounded-md text-primary-600 hover:bg-primary-50 transition">
                                    <x-ui.icon name="user" class="w-[22px] h-[22px]" />
                                    <span class="text-[11px] font-medium text-neutral-600 max-w-[8rem] truncate">{{ $user->name ?? $user->email }}</span>
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
                        <a href="/connexion" class="flex flex-col items-center gap-0.5 px-2.5 py-1.5 rounded-md text-primary-600 hover:bg-primary-50 transition">
                            <x-ui.icon name="user" class="w-[22px] h-[22px]" />
                            <span class="text-[11px] font-medium text-neutral-600">Compte pro</span>
                        </a>
                    @endif

                    <a href="/achat-rapide" class="hidden lg:flex flex-col items-center gap-0.5 px-2.5 py-1.5 rounded-md text-primary-600 hover:bg-primary-50 transition">
                        <x-ui.icon name="lightning" class="w-[22px] h-[22px]" />
                        <span class="text-[11px] font-medium text-neutral-600">Achat rapide</span>
                    </a>

                    @auth
                        <button type="button" x-data @click="$dispatch('open-cart-drawer')" class="relative flex flex-col items-center gap-0.5 px-2.5 py-1.5 rounded-md text-primary-600 hover:bg-primary-50 transition">
                            <div class="relative">
                                <x-ui.icon name="cart" class="w-[22px] h-[22px]" />
                                @if ($cartCount > 0)
                                    <span class="absolute -top-2 -right-2.5 bg-accent-500 text-primary-700 text-[11px] font-bold font-mono rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">{{ $cartCount }}</span>
                                @endif
                            </div>
                            <span class="text-[11px] font-medium text-neutral-600">Panier</span>
                        </button>
                    @else
                        <a href="/connexion" class="relative flex flex-col items-center gap-0.5 px-2.5 py-1.5 rounded-md text-primary-600 hover:bg-primary-50 transition">
                            <x-ui.icon name="cart" class="w-[22px] h-[22px]" />
                            <span class="text-[11px] font-medium text-neutral-600">Panier</span>
                        </a>
                    @endauth
                </div>
            </div>

            {{-- Mobile search (row 2) --}}
            <div class="md:hidden pb-3">
                <x-layout.search-bar />
            </div>

            {{-- Category nav (white, lime underline on active) --}}
            <nav class="hidden lg:flex items-stretch gap-1 h-12 -mb-px overflow-x-auto no-scrollbar">
                <button
                    type="button"
                    x-data
                    @click="$dispatch('open-lateral-menu')"
                    class="flex items-center gap-2 px-4 font-semibold text-sm text-primary-600 border-b-[3px] border-transparent hover:border-accent-500 transition shrink-0"
                >
                    <x-ui.icon name="menu" class="w-[18px] h-[18px]" />
                    <span>Tous nos produits</span>
                </button>

                @foreach ($nav as $item)
                    <a href="{{ $item['href'] ?? '#' }}" class="flex items-center gap-2 px-4 font-semibold text-sm text-neutral-600 border-b-[3px] border-transparent hover:text-primary-600 hover:border-accent-500 transition shrink-0">
                        @if (! empty($item['icon']))<x-ui.icon :name="$item['icon']" class="w-[18px] h-[18px]" />@endif
                        {{ $item['label'] }}
                    </a>
                @endforeach

                <a href="{{ $quoteUrl }}" class="ml-auto flex items-center gap-2 px-4 font-semibold text-sm text-accent-700 hover:text-accent-800 transition shrink-0">
                    <x-ui.icon name="document" class="w-[18px] h-[18px]" /> Demander un devis
                </a>
            </nav>
        </div>
    </div>

    {{-- Info banner (optionnel) --}}
    @if (config('storefront.banner.enabled'))
        <div class="bg-accent-50 border-b border-accent-100 text-primary-800 text-sm">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-center gap-2 font-medium">
                <x-ui.icon name="{{ config('storefront.banner.icon', 'truck') }}" class="w-4 h-4 text-accent-600" />
                <span>{{ config('storefront.banner.text') }}</span>
            </div>
        </div>
    @endif
</header>
