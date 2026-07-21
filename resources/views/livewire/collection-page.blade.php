<section class="py-8 md:py-12">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Fil d'Ariane hiérarchique : sur une navigation en cascade, afficher
             la seule catégorie courante perdrait le visiteur au 3ᵉ niveau. --}}
        <x-ui.breadcrumb class="mb-4" :items="$this->breadcrumbItems" />

        <header class="mb-8">
            <h1 class="font-display font-bold text-3xl md:text-4xl text-neutral-900">
                {{ $this->collection->translateAttribute('name') }}
            </h1>
            @if ($this->collection->translateAttribute('description'))
                <div class="mt-2 text-neutral-600 max-w-3xl">
                    {!! $this->collection->translateAttribute('description') !!}
                </div>
            @endif
            <p class="mt-3 text-sm text-neutral-500">
                @if ($showsChildCards)
                    {{ $childCollections->count() }} {{ Str::plural('catégorie', $childCollections->count()) }}
                @else
                    {{ $products->total() }} produits
                @endif
            </p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-8">
            <aside>
                <div class="lg:sticky lg:top-28 space-y-4">
                    <div class="bg-white border border-neutral-200 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <h2 class="flex items-center gap-2 font-display font-bold text-neutral-900 text-base">
                                <x-ui.icon name="sliders" class="w-[18px] h-[18px] text-primary-600" /> Filtres
                            </h2>
                            @if (! empty($this->selectedValueIds) || ! empty(array_filter($selectedBrands)))
                                <button type="button" wire:click="clearFilters" class="text-xs text-primary-600 hover:text-primary-700 font-semibold">Réinitialiser</button>
                            @endif
                        </div>
                    </div>

                    @if ($brands->isNotEmpty())
                        <div x-data="{ open: true }" class="bg-white border border-neutral-200 rounded-xl overflow-hidden">
                            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 font-semibold text-sm text-neutral-900 hover:bg-neutral-50">
                                <span>Marque</span>
                                <x-ui.icon name="chevron-down" class="w-4 h-4 transition" x-bind:class="open ? 'rotate-180' : ''" />
                            </button>
                            <div x-show="open" class="px-4 pb-3 space-y-2 max-h-60 overflow-y-auto">
                                @foreach ($brands as $brand)
                                    @php $checked = ! empty($selectedBrands[$brand->id]); @endphp
                                    <label class="flex items-center justify-between gap-2 cursor-pointer text-sm text-neutral-700 hover:text-primary-700">
                                        <span class="flex items-center gap-2">
                                            <input type="checkbox" wire:click="toggleBrand({{ $brand->id }})" @checked($checked) class="rounded border-neutral-300 text-accent-600 focus:ring-accent-500" />
                                            {{ $brand->name }}
                                        </span>
                                        <span class="text-xs text-neutral-400">{{ $brand->products_count }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @forelse ($families as $family)
                        <div x-data="{ open: true }" class="bg-white border border-neutral-200 rounded-xl overflow-hidden">
                            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 font-semibold text-sm text-neutral-900 hover:bg-neutral-50">
                                <span>{{ $family->label }}</span>
                                <x-ui.icon name="chevron-down" class="w-4 h-4 transition" x-bind:class="open ? 'rotate-180' : ''" />
                            </button>
                            <div x-show="open" class="px-4 pb-3 space-y-2">
                                @foreach ($family->values->sortBy('position') as $value)
                                    @php $count = (int) ($family->value_counts[$value->id] ?? 0); @endphp
                                    @if ($count === 0) @continue @endif
                                    @php $checked = ! empty($selected[$family->id][$value->id]); @endphp
                                    <label class="flex items-center justify-between gap-2 cursor-pointer text-sm text-neutral-700 hover:text-primary-700">
                                        <span class="flex items-center gap-2">
                                            <input type="checkbox" wire:click="toggleValue({{ $family->id }}, {{ $value->id }})" @checked($checked) class="rounded border-neutral-300 text-accent-600 focus:ring-accent-500" />
                                            {{ $value->label }}
                                        </span>
                                        <span class="text-xs text-neutral-400">{{ $count }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        @if ($brands->isEmpty())
                            <div class="bg-white border border-neutral-200 rounded-lg p-5 text-center text-sm text-neutral-500">
                                Pas encore de filtres disponibles pour cette catégorie.
                            </div>
                        @endif
                    @endforelse
                </div>
            </aside>

            <div>
                {{-- Mode « page de listing de catégories » : on aiguille vers le
                     niveau inférieur au lieu de vendre. Le tri et la pagination
                     produits n'ont pas d'objet ici. --}}
                @if ($showsChildCards)
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                        @foreach ($childCollections as $child)
                            @php($childImage = pko_media_url($child->getFirstMedia('images'), 'medium'))
                            <a href="{{ $child->defaultUrl?->slug ? route('collection.view', $child->defaultUrl->slug) : '#' }}"
                               wire:key="child-{{ $child->id }}"
                               class="group flex flex-col rounded-xl bg-white shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                                {{-- `relative` + enfant `absolute inset-0` : l'image sort
                                     du flux, elle ne peut donc pas étirer le conteneur
                                     au-delà du ratio (un flex item garde `min-height: auto`
                                     et s'étend sinon à la hauteur naturelle de l'image). --}}
                                <div class="relative aspect-[4/3] overflow-hidden">
                                    <div class="absolute inset-0 flex items-center justify-center p-6">
                                        @if ($childImage)
                                            <img src="{{ $childImage }}"
                                                 alt="{{ $child->translateAttribute('name') }}"
                                                 loading="lazy"
                                                 class="w-full h-full object-contain" />
                                        @else
                                            <x-ui.icon name="shopping-bag" class="w-10 h-10 text-neutral-300" />
                                        @endif
                                    </div>
                                </div>
                                <div class="px-4 pb-4">
                                    <h2 class="font-semibold text-primary-800 group-hover:text-primary-600 transition-colors">
                                        {{ $child->translateAttribute('name') }}
                                    </h2>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                <div class="flex items-center justify-between mb-5">
                    <div class="text-sm text-neutral-500">
                        Affichage {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} / {{ $products->total() }}
                    </div>
                    <div class="flex items-center gap-2">
                        <label for="sort" class="text-sm text-neutral-500">Trier :</label>
                        <select id="sort" wire:model.live="sort" class="rounded-md border-neutral-300 text-sm font-medium focus:border-accent-500 focus:ring-accent-500">
                            <option value="new">Nouveautés</option>
                            <option value="price-asc">Prix croissant</option>
                            <option value="price-desc">Prix décroissant</option>
                            <option value="name-asc">Nom A-Z</option>
                        </select>
                    </div>
                </div>

                @if ($products->isEmpty())
                    <x-ui.card padding="lg" class="text-center">
                        <p class="text-neutral-500 py-8">Aucun produit ne correspond à vos critères.</p>
                        @if (! empty($this->selectedValueIds))
                            <x-ui.button variant="outline" wire:click="clearFilters">Réinitialiser les filtres</x-ui.button>
                        @endif
                    </x-ui.card>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        @foreach ($products as $product)
                            <x-storefront.product-card :product="$product" wire:key="product-{{ $product->id }}" />
                        @endforeach
                    </div>
                    <div class="mt-8">{{ $products->links() }}</div>
                @endif
                @endif
            </div>
        </div>
    </div>
</section>
