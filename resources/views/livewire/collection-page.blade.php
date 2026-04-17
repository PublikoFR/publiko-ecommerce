<section class="py-8 md:py-12">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-ui.breadcrumb class="mb-4" :items="[
            ['label' => $this->collection->translateAttribute('name')],
        ]" />

        <header class="mb-8">
            <h1 class="text-3xl md:text-4xl font-black text-neutral-900">
                {{ $this->collection->translateAttribute('name') }}
            </h1>
            @if ($this->collection->translateAttribute('description'))
                <div class="mt-2 text-neutral-600 max-w-3xl">
                    {!! $this->collection->translateAttribute('description') !!}
                </div>
            @endif
            <p class="mt-3 text-sm text-neutral-500">{{ $products->total() }} produits</p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-8">
            <aside>
                <div class="lg:sticky lg:top-28 space-y-4">
                    @if (! empty($this->selectedValueIds))
                        <x-ui.button variant="ghost" size="sm" wire:click="clearFilters" class="w-full">
                            Effacer les filtres ({{ count($this->selectedValueIds) }})
                        </x-ui.button>
                    @endif

                    @forelse ($families as $family)
                        <div x-data="{ open: true }" class="bg-white border border-neutral-200 rounded-lg overflow-hidden">
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
                                            <input type="checkbox" wire:click="toggleValue({{ $family->id }}, {{ $value->id }})" @checked($checked) class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500" />
                                            {{ $value->label }}
                                        </span>
                                        <span class="text-xs text-neutral-400">{{ $count }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-neutral-500 italic">Aucun filtre disponible.</p>
                    @endforelse
                </div>
            </aside>

            <div>
                <div class="flex items-center justify-between mb-5">
                    <div class="text-sm text-neutral-500">
                        Affichage {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} / {{ $products->total() }}
                    </div>
                    <div class="flex items-center gap-2">
                        <label for="sort" class="text-sm text-neutral-500">Trier :</label>
                        <select id="sort" wire:model.live="sort" class="rounded-md border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
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
            </div>
        </div>
    </div>
</section>
