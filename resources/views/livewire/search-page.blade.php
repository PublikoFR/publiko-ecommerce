<section class="py-8 md:py-12">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-ui.breadcrumb class="mb-4" :items="[
            ['label' => 'Recherche'],
        ]" />

        <header class="mb-8">
            <h1 class="text-3xl md:text-4xl font-black text-neutral-900">
                Recherche
                @if ($term) <span class="text-neutral-600 font-normal">« {{ $term }} »</span>@endif
            </h1>
            <p class="mt-3 text-sm text-neutral-500">{{ $products->total() }} produits</p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-8">
            <aside>
                <div class="lg:sticky lg:top-28 space-y-4">
                    <div class="bg-white border border-neutral-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="font-bold text-neutral-900 text-sm uppercase tracking-wider">Filtres</h2>
                            @if (! empty(array_filter($selected)) || ! empty(array_filter($selectedBrands)))
                                <button type="button" wire:click="clearFilters" class="text-xs text-primary-600 hover:text-primary-700 font-semibold">Réinitialiser</button>
                            @endif
                        </div>
                    </div>

                    @if ($brands->isNotEmpty())
                        <div x-data="{ open: true }" class="bg-white border border-neutral-200 rounded-lg overflow-hidden">
                            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 font-semibold text-sm text-neutral-900 hover:bg-neutral-50">
                                <span>Marque</span>
                                <x-ui.icon name="chevron-down" class="w-4 h-4 transition" x-bind:class="open ? 'rotate-180' : ''" />
                            </button>
                            <div x-show="open" class="px-4 pb-3 space-y-2 max-h-60 overflow-y-auto">
                                @foreach ($brands as $brand)
                                    @php $checked = ! empty($selectedBrands[$brand->id]); @endphp
                                    <label class="flex items-center justify-between gap-2 cursor-pointer text-sm text-neutral-700 hover:text-primary-700">
                                        <span class="flex items-center gap-2">
                                            <input type="checkbox" wire:click="toggleBrand({{ $brand->id }})" @checked($checked) class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500" />
                                            {{ $brand->name }}
                                        </span>
                                        <span class="text-xs text-neutral-400">{{ $brand->products_count }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @foreach ($families as $family)
                        <div x-data="{ open: true }" class="bg-white border border-neutral-200 rounded-lg overflow-hidden">
                            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 font-semibold text-sm text-neutral-900 hover:bg-neutral-50">
                                <span>{{ $family->name }}</span>
                                <x-ui.icon name="chevron-down" class="w-4 h-4 transition" x-bind:class="open ? 'rotate-180' : ''" />
                            </button>
                            <div x-show="open" class="px-4 pb-3 space-y-2">
                                @foreach ($family->values->sortBy('position') as $value)
                                    @php $count = (int) ($family->value_counts[$value->id] ?? 0); @endphp
                                    @if ($count === 0) @continue @endif
                                    @php $checked = ! empty($selected[$family->id][$value->id]); @endphp
                                    <label class="flex items-center justify-between gap-2 cursor-pointer text-sm text-neutral-700 hover:text-primary-700">
                                        <span class="flex items-center gap-2">
                                            <input type="checkbox" wire:click="toggleValue({{ $family->id }}, {{ $value->id }})" @checked($checked) class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500" />
                                            {{ $value->name }}
                                        </span>
                                        <span class="text-xs text-neutral-400">{{ $count }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </aside>

            <div>
                @if ($products->isEmpty())
                    <div class="bg-white border border-neutral-200 rounded-lg p-8 text-center">
                        <p class="text-neutral-500 py-4">Aucun produit ne correspond à votre recherche.</p>
                        @if (! empty(array_filter($selected)) || ! empty(array_filter($selectedBrands)))
                            <button type="button" wire:click="clearFilters" class="text-sm text-primary-600 hover:text-primary-700 font-semibold">Réinitialiser les filtres</button>
                        @endif
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        @foreach ($products as $product)
                            <x-storefront.product-card :product="$product" wire:key="product-{{ $product->id }}" />
                        @endforeach
                    </div>
                    <div class="mt-8">{{ $products->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</section>
