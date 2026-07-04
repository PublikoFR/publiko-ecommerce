@props(['product'])

@php
$slug = $product->defaultUrl?->slug;
$url = $slug ? route('product.view', $slug) : '#';
$thumb = $product->thumbnail;
$brand = $product->brand?->name;
$firstVariant = $product->variants->first();
$code = $firstVariant?->sku;
$variantsCount = $product->variants->count();
$isNew = optional($product->created_at)->gt(now()->subDays(30));
$stock = (int) ($firstVariant?->stock ?? 0);

// Client pro connecté ? (prix + achat réservés aux pros)
$user = auth()->user();
$isPro = false;
if ($user !== null) {
    $customer = method_exists($user, 'customers') ? $user->customers()->first() : null;
    $isPro = $customer !== null && $customer->getAttribute('sirene_status') === 'active';
}

// Statut de stock → tonalité DS
if ($stock <= 0) {
    $stockTone = 'neutral'; $stockLabel = 'Sur commande';
} elseif ($stock <= 5) {
    $stockTone = 'warning'; $stockLabel = 'Stock faible';
} else {
    $stockTone = 'success'; $stockLabel = 'En stock';
}
@endphp

<article class="group bg-white border border-neutral-200 rounded-xl overflow-hidden flex flex-col transition duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:border-neutral-300">
    <a href="{{ $url }}" wire:navigate class="block relative aspect-[4/3] overflow-hidden"
       style="background: radial-gradient(120% 120% at 30% 20%, #ffffff 0%, var(--surface-brand-soft) 90%);">
        <div class="absolute inset-0 p-5 flex items-center justify-center">
            @if ($thumb)
                <img src="{{ $thumb->getUrl('medium') }}" alt="{{ $product->translateAttribute('name') }}" loading="lazy" class="max-w-full max-h-full object-contain transition duration-300 group-hover:scale-105" />
            @else
                <x-ui.icon name="package" class="w-16 h-16 text-primary-200" />
            @endif
        </div>

        @if ($isNew)
            <span class="absolute top-3 left-3"><x-ui.badge tone="lime" variant="solid" size="sm">Nouveau</x-ui.badge></span>
        @endif
    </a>

    <div class="p-4 flex flex-col flex-1 gap-1.5">
        <div class="flex items-center justify-between gap-2">
            @if ($brand)<span class="font-mono text-[11px] text-neutral-500 truncate">{{ $brand }}</span>@else<span></span>@endif
            <x-ui.badge :tone="$stockTone" size="sm" dot>{{ $stockLabel }}</x-ui.badge>
        </div>

        <a href="{{ $url }}" wire:navigate class="block">
            <h3 class="text-sm font-semibold text-neutral-900 leading-snug line-clamp-2 min-h-[2.5rem] group-hover:text-primary-700 transition">
                {{ $product->translateAttribute('name') }}
            </h3>
        </a>

        <div class="font-mono text-[11px] text-neutral-500">
            @if ($code)Réf. {{ $code }}@endif
            @if ($variantsCount > 1)<span class="ml-2">· {{ $variantsCount }} variantes</span>@endif
        </div>

        <div class="mt-auto pt-3">
            @if ($isPro)
                <div class="flex items-end justify-between gap-3">
                    <x-storefront.price-gate :product="$product" size="md" class="min-w-0" />
                    <div class="shrink-0">
                        <x-storefront.add-to-cart :product="$product" :variant="$firstVariant" style="compact" />
                    </div>
                </div>
            @else
                <div class="flex flex-col gap-2.5">
                    <p class="text-xs text-neutral-500">Prix réservé aux professionnels</p>
                    <a href="/connexion" class="inline-flex items-center justify-center gap-1.5 w-full h-9 text-xs font-semibold text-white bg-primary-600 rounded-md hover:bg-primary-700 transition">
                        <x-ui.icon name="user" class="w-4 h-4" /> Se connecter
                    </a>
                </div>
            @endif
        </div>
    </div>
</article>
