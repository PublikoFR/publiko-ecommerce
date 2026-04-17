<section class="py-8 md:py-12">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-ui.breadcrumb class="mb-6" :items="[
            ['label' => $this->product->translateAttribute('name')],
        ]" />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            {{-- Gallery --}}
            <div>
                <div class="bg-white border border-neutral-200 rounded-lg overflow-hidden aspect-square flex items-center justify-center p-8">
                    @if ($this->image)
                        <img class="max-w-full max-h-full object-contain" src="{{ $this->image->getUrl('large') }}" alt="{{ $this->product->translateAttribute('name') }}" />
                    @else
                        <x-ui.icon name="shopping-bag" class="w-24 h-24 text-neutral-200" />
                    @endif
                </div>

                @if ($this->images->count() > 1)
                    <div class="mt-4 grid grid-cols-4 gap-3">
                        @foreach ($this->images as $image)
                            <div wire:key="image_{{ $image->id }}" class="bg-white border border-neutral-200 rounded overflow-hidden aspect-square p-2 flex items-center justify-center">
                                <img loading="lazy" class="max-w-full max-h-full object-contain" src="{{ $image->getUrl('small') }}" alt="" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Details --}}
            <div>
                @if ($this->product->brand?->name)
                    <p class="text-xs font-bold text-primary-600 uppercase tracking-widest mb-2">{{ $this->product->brand->name }}</p>
                @endif

                <h1 class="text-2xl md:text-3xl font-black text-neutral-900 leading-tight">
                    {{ $this->product->translateAttribute('name') }}
                </h1>

                <p class="mt-2 text-sm text-neutral-500">
                    Code <span class="font-semibold text-neutral-700">{{ $this->variant->sku }}</span>
                </p>

                @if ($this->product->translateAttribute('description'))
                    <article class="mt-5 text-sm text-neutral-700 leading-relaxed prose prose-sm max-w-none">
                        {!! $this->product->translateAttribute('description') !!}
                    </article>
                @endif

                <form class="mt-6 space-y-6">
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
                                        class="px-4 py-2 text-sm font-medium border rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                        :class="selectedValues.includes({{ $value->id }})
                                            ? 'bg-primary-600 border-primary-600 text-white'
                                            : 'bg-white border-neutral-300 text-neutral-700 hover:border-neutral-400 hover:bg-neutral-50'">
                                        {{ $value->translate('name') }}
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="border-t border-neutral-200 pt-6">
                        <div class="mb-4">
                            <x-storefront.price-gate :variant="$this->variant" size="xl" />
                        </div>
                        <div class="max-w-md">
                            <x-storefront.add-to-cart :product="$this->product" :variant="$this->variant" />
                        </div>
                    </div>
                </form>

                <div class="mt-8 pt-6 border-t border-neutral-200 flex flex-wrap gap-6 text-sm">
                    <div class="flex items-center gap-2 text-neutral-600"><x-ui.icon name="truck" class="w-5 h-5 text-primary-600" /> Livraison 24/48h</div>
                    <div class="flex items-center gap-2 text-neutral-600"><x-ui.icon name="map-pin" class="w-5 h-5 text-primary-600" /> Retrait en magasin</div>
                    <div class="flex items-center gap-2 text-neutral-600"><x-ui.icon name="check" class="w-5 h-5 text-success-600" /> Support pro dédié</div>
                </div>
            </div>
        </div>
    </div>
</section>
