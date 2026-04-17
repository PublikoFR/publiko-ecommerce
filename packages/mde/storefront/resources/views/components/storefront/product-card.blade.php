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
@endphp

<article class="group bg-white border border-neutral-200 rounded-lg overflow-hidden flex flex-col transition hover:shadow-lg hover:border-neutral-300">
    <a href="{{ $url }}" wire:navigate class="block relative aspect-square bg-neutral-50 overflow-hidden">
        <div class="absolute inset-0 p-4 flex items-center justify-center">
            @if ($thumb)
                <img src="{{ $thumb->getUrl('medium') }}" alt="{{ $product->translateAttribute('name') }}" loading="lazy" class="max-w-full max-h-full object-contain transition duration-300 group-hover:scale-105" />
            @else
                <div class="w-full h-full bg-neutral-100 rounded flex items-center justify-center text-neutral-300">
                    <x-ui.icon name="shopping-bag" class="w-16 h-16" />
                </div>
            @endif
        </div>

        @if ($isNew)
            <span class="absolute top-2 left-2"><x-ui.badge variant="new">Nouveau</x-ui.badge></span>
        @endif

        @if ($brand)
            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm px-2 py-1 rounded text-[11px] font-bold text-neutral-700 uppercase tracking-wider">
                {{ $brand }}
            </div>
        @endif
    </a>

    <div class="p-4 flex flex-col flex-1">
        <a href="{{ $url }}" wire:navigate class="block mb-2">
            <h3 class="text-sm font-semibold text-neutral-900 line-clamp-2 min-h-[2.5rem] group-hover:text-primary-700 transition">
                {{ $product->translateAttribute('name') }}
            </h3>
        </a>

        <div class="text-xs text-neutral-500 mb-3">
            @if ($code)<span>Code {{ $code }}</span>@endif
            @if ($variantsCount > 1)<span class="ml-2 inline-flex items-center gap-1"><span class="w-1 h-1 bg-neutral-300 rounded-full"></span> {{ $variantsCount }} variantes</span>@endif
        </div>

        <div class="mt-auto pt-3 border-t border-neutral-100">
            <div class="mb-3">
                <x-storefront.price-gate :product="$product" size="md" />
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="flex-1 inline-flex items-center justify-center gap-1 px-2 py-2 text-xs font-semibold text-neutral-700 border border-neutral-200 rounded hover:bg-neutral-50 hover:border-neutral-300 transition" title="Ajouter à une liste d'achat" aria-label="Ajouter à une liste d'achat">
                    <x-ui.icon name="list" class="w-4 h-4" /> Liste
                </button>
                <x-storefront.add-to-cart :product="$product" :variant="$firstVariant" style="compact" />
            </div>
        </div>
    </div>
</article>
