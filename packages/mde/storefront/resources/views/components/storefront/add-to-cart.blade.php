@props(['product' => null, 'variant' => null, 'style' => 'default'])

@php
$user = auth()->user();
$isPro = false;
if ($user !== null) {
    $customer = method_exists($user, 'customers') ? $user->customers()->first() : null;
    $isPro = $customer !== null && $customer->getAttribute('sirene_status') === 'active';
}

$slug = $product?->defaultUrl?->slug;
$productUrl = $slug ? route('product.view', $slug) : null;
$variantsCount = $product?->variants->count() ?? 1;
$target = $variant ?: $product?->variants?->first();
@endphp

@if (! $isPro)
    @if ($style === 'compact')
        <a href="/connexion" class="flex-1 inline-flex items-center justify-center gap-1 px-2 py-2 text-xs font-semibold text-white bg-primary-600 rounded hover:bg-primary-700 transition" title="Connexion requise" aria-label="Connectez-vous pour commander">
            <x-ui.icon name="cart" class="w-4 h-4" /> Connexion
        </a>
    @else
        <x-ui.button variant="primary" icon="cart" href="/connexion" class="w-full justify-center">Connectez-vous pour commander</x-ui.button>
    @endif
@elseif ($variantsCount > 1 && $productUrl)
    @if ($style === 'compact')
        <a href="{{ $productUrl }}" wire:navigate class="flex-1 inline-flex items-center justify-center gap-1 px-2 py-2 text-xs font-semibold text-white bg-primary-600 rounded hover:bg-primary-700 transition">
            <x-ui.icon name="cart" class="w-4 h-4" /> Choisir
        </a>
    @else
        <x-ui.button variant="primary" icon="cart" :href="$productUrl" class="w-full justify-center">Choisir une variante</x-ui.button>
    @endif
@else
    @if ($target)
        @livewire('components.add-to-cart', ['purchasable' => $target], key('atc-'.$target->id.'-'.$style))
    @endif
@endif
