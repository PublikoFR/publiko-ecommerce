<section class="py-6 md:py-10">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-ui.breadcrumb class="mb-6" :items="[
            ['label' => $this->product->translateAttribute('name')],
        ]" />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            {{-- Gallery --}}
            <div>
                <div class="border border-neutral-200 rounded-2xl overflow-hidden aspect-square flex items-center justify-center p-8"
                     style="background: radial-gradient(120% 120% at 30% 20%, #ffffff 0%, var(--surface-brand-soft) 90%);">
                    @if ($this->image)
                        <img class="max-w-full max-h-full object-contain" src="{{ $this->image->getUrl('large') }}" alt="{{ $this->product->translateAttribute('name') }}" />
                    @else
                        <x-ui.icon name="package" class="w-28 h-28 text-primary-200" strokeWidth="1.2" />
                    @endif
                </div>

                @if ($this->images->count() > 1)
                    <div class="mt-4 grid grid-cols-4 gap-3">
                        @foreach ($this->images as $image)
                            <div wire:key="image_{{ $image->id }}" class="bg-white border border-neutral-200 rounded-md overflow-hidden aspect-square p-2 flex items-center justify-center hover:border-primary-600 transition">
                                <img loading="lazy" class="max-w-full max-h-full object-contain" src="{{ $image->getUrl('small') }}" alt="" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Details --}}
            <div>
                <div class="flex items-center gap-3 mb-2">
                    @if ($this->product->brand?->name)
                        <span class="font-mono text-xs text-neutral-500">{{ $this->product->brand->name }}</span>
                    @endif
                    @if ($this->variant->stock > 0)
                        <x-ui.badge tone="success" size="sm" dot>En stock</x-ui.badge>
                    @elseif ($this->supplier)
                        <x-ui.badge tone="warning" size="sm" dot>Sur commande</x-ui.badge>
                    @endif
                </div>

                <h1 class="font-display font-bold text-2xl md:text-3xl text-neutral-900 leading-tight">
                    {{ $this->product->translateAttribute('name') }}
                </h1>

                <p class="mt-2 font-mono text-xs text-neutral-500">
                    Réf. <span class="text-neutral-700">{{ $this->variant->sku }}</span>
                </p>

                @if ($this->product->translateAttribute('description'))
                    <article class="mt-5 text-sm text-neutral-700 leading-relaxed prose prose-sm max-w-none">
                        {!! $this->product->translateAttribute('description') !!}
                    </article>
                @endif

                <form class="mt-6">
                    <div class="space-y-5 mb-6">
                        @foreach ($this->productOptions as $option)
                            <fieldset>
                                <legend class="text-sm font-semibold text-neutral-800 mb-2">
                                    {{ $option['option']->translate('name') }}
                                </legend>

                                <div class="flex flex-wrap gap-2" x-data="{
                                    selectedOption: @entangle('selectedOptionValues').live,
                                    selectedValues: [],
                                }" x-init="selectedValues = Object.values(selectedOption);
                                    $watch('selectedOption', value => selectedValues = Object.values(selectedOption))">
                                    @foreach ($option['values'] as $value)
                                        <button type="button" wire:click="$set('selectedOptionValues.{{ $option['option']->id }}', {{ $value->id }})"
                                            class="px-4 py-2 text-sm font-medium border rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                                            :class="selectedValues.includes({{ $value->id }})
                                                ? 'bg-primary-600 border-primary-600 text-white'
                                                : 'bg-white border-neutral-300 text-neutral-700 hover:border-neutral-400 hover:bg-neutral-50'">
                                            {{ $value->translate('name') }}
                                        </button>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>

                    {{-- Price card (Design System) --}}
                    <div class="bg-white border border-neutral-200 rounded-xl p-6">
                        <x-storefront.price-gate :variant="$this->variant" size="xl" />
                        @auth
                            <div class="inline-flex items-center gap-1.5 mt-2 text-success-700 text-[13px] font-semibold">
                                <x-ui.icon name="badge-check" class="w-4 h-4 text-success-500" /> Tarif pro · remises dégressives par volume
                            </div>
                        @endauth
                        <div class="mt-5 max-w-md">
                            <x-storefront.add-to-cart :product="$this->product" :variant="$this->variant" />
                        </div>
                    </div>
                </form>

                {{-- Reassurance grid --}}
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    @if ($this->product->pko_free_shipping)
                        <div class="flex items-center gap-2.5 text-success-700 font-semibold"><x-ui.icon name="truck" class="w-5 h-5 text-success-600" /> Livraison offerte</div>
                    @elseif ($this->variant->stock > 0)
                        <div class="flex items-center gap-2.5 text-neutral-700"><x-ui.icon name="truck" class="w-5 h-5 text-primary-600" /> En stock — expédition 24/48 h</div>
                    @elseif ($this->supplier)
                        <div class="flex items-center gap-2.5 text-warning-700"><x-ui.icon name="truck" class="w-5 h-5 text-warning-500" /> Sur commande — {{ $this->supplier->lead_time_min_days }} à {{ $this->supplier->lead_time_max_days }} j ouvrés</div>
                    @else
                        <div class="flex items-center gap-2.5 text-neutral-700"><x-ui.icon name="truck" class="w-5 h-5 text-primary-600" /> Livraison 24/48 h</div>
                    @endif
                    <div class="flex items-center gap-2.5 text-neutral-700"><x-ui.icon name="map-pin" class="w-5 h-5 text-primary-600" /> Retrait en magasin</div>
                    <div class="flex items-center gap-2.5 text-neutral-700"><x-ui.icon name="shield-check" class="w-5 h-5 text-primary-600" /> Produits certifiés</div>
                    <div class="flex items-center gap-2.5 text-neutral-700"><x-ui.icon name="headset" class="w-5 h-5 text-primary-600" /> Support pro dédié</div>
                </div>
            </div>
        </div>
    </div>
</section>

@if ($this->documents->isNotEmpty())
    <section class="pb-10 md:pb-14">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display font-bold text-xl text-neutral-900 mb-6">Documents téléchargeables</h2>

            <div class="space-y-6">
                @foreach ($this->documents as $categoryLabel => $docs)
                    <div>
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase tracking-[0.08em] mb-3">
                            {{ $categoryLabel }}
                        </h3>
                        <ul class="flex flex-col gap-2.5 max-w-xl">
                            @foreach ($docs as $doc)
                                @if ($doc->media)
                                    <li>
                                        <a
                                            href="{{ $doc->media->getUrl() }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="flex items-center gap-3 px-4 py-3.5 rounded-md border border-neutral-200 bg-white text-sm text-neutral-800 hover:border-neutral-300 hover:shadow-sm transition"
                                        >
                                            <x-ui.icon name="document" class="w-5 h-5 shrink-0 text-danger-500" />
                                            <span class="flex-1 font-semibold">
                                                {{ $doc->media->name ?: $doc->media->file_name }}
                                            </span>
                                            <x-ui.icon name="download" class="w-[18px] h-[18px] shrink-0 text-neutral-400" />
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
