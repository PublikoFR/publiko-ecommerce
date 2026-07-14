<div class="relative flex-1" x-data @click.away="$wire.close()">
    <form wire:submit="submitSearch" role="search"
          class="flex items-center h-12 bg-neutral-50 border-[1.5px] border-neutral-300 rounded-full pl-4 pr-1.5 focus-within:border-accent-500 focus-within:ring-2 focus-within:ring-accent-500/40 transition">
        <x-ui.icon name="search" class="w-5 h-5 text-neutral-400 shrink-0" />
        <input
            type="search"
            wire:model.live.debounce.300ms="term"
            placeholder="Rechercher un produit, une référence…"
            class="flex-1 min-w-0 border-0 bg-transparent focus:ring-0 text-sm placeholder:text-neutral-400 px-3"
            aria-label="Rechercher"
            autocomplete="off"
        />
        <button type="submit" class="inline-flex items-center justify-center h-9 bg-primary-600 hover:bg-primary-700 text-white px-4 rounded-full font-semibold text-sm transition shrink-0">
            <span class="hidden sm:inline">Rechercher</span>
            <x-ui.icon name="search" class="w-5 h-5 sm:hidden" />
        </button>
    </form>

    @if ($open)
        <div class="absolute top-full left-0 right-0 mt-2 bg-white border border-neutral-200 rounded-xl shadow-xl z-50 max-h-[32rem] overflow-y-auto">
            @if ($products->isEmpty() && $brands->isEmpty() && $collections->isEmpty())
                <div class="p-6 text-center text-sm text-neutral-500">Aucun résultat pour "{{ $term }}".</div>
            @else
                @if ($brands->isNotEmpty())
                    <div class="p-2">
                        <p class="px-3 py-1.5 text-xs font-bold text-neutral-500 uppercase tracking-wider">Marques</p>
                        @foreach ($brands as $brand)
                            <a href="/recherche?term={{ urlencode($brand->name) }}" class="block px-3 py-2 rounded text-sm hover:bg-primary-50 hover:text-primary-700 transition">{{ $brand->name }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($collections->isNotEmpty())
                    <div class="p-2 border-t border-neutral-100">
                        <p class="px-3 py-1.5 text-xs font-bold text-neutral-500 uppercase tracking-wider">Catégories</p>
                        @foreach ($collections as $collection)
                            <a href="{{ $collection->defaultUrl?->slug ? route('collection.view', $collection->defaultUrl->slug) : '#' }}" class="block px-3 py-2 rounded text-sm hover:bg-primary-50 hover:text-primary-700 transition">{{ $collection->translateAttribute('name') }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($products->isNotEmpty())
                    <div class="p-2 border-t border-neutral-100">
                        <p class="px-3 py-1.5 text-xs font-bold text-neutral-500 uppercase tracking-wider">Produits ({{ $products->count() }})</p>
                        @foreach ($products as $product)
                            <a href="{{ $product->defaultUrl?->slug ? route('product.view', $product->defaultUrl->slug) : '#' }}" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-primary-50 transition">
                                <div class="w-10 h-10 bg-neutral-50 rounded border border-neutral-100 flex items-center justify-center p-1 shrink-0">
                                    @php($thumb = pko_product_thumbnail($product))
                                    @if ($thumb)
                                        <img src="{{ pko_media_url($thumb, 'small') }}" alt="" class="max-w-full max-h-full object-contain" />
                                    @else
                                        <x-ui.icon name="shopping-bag" class="w-5 h-5 text-neutral-300" />
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-neutral-900 truncate">{{ $product->translateAttribute('name') }}</p>
                                    <p class="text-xs text-neutral-500 truncate">
                                        @if ($product->brand?->name){{ $product->brand->name }} · @endif
                                        {{ $product->variants->first()?->sku }}
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="p-2 border-t border-neutral-100">
                    <button type="button" wire:click="submitSearch" class="w-full text-center text-sm font-semibold text-primary-600 py-2 hover:bg-primary-50 rounded transition">
                        Voir tous les résultats pour "{{ $term }}" →
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
