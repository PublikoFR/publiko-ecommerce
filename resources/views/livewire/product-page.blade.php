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

@if ($this->documents->isNotEmpty())
    <section class="pb-10 md:pb-14">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-lg font-bold text-neutral-900 mb-6">Documents téléchargeables</h2>

            <div class="space-y-6">
                @foreach ($this->documents as $categoryLabel => $docs)
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-500 uppercase tracking-wider mb-3">
                            {{ $categoryLabel }}
                        </h3>
                        <ul class="divide-y divide-neutral-100 rounded-lg border border-neutral-200 bg-white overflow-hidden">
                            @foreach ($docs as $doc)
                                @if ($doc->media)
                                    <li>
                                        <a
                                            href="{{ $doc->media->getUrl() }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="flex items-center gap-3 px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-50 transition-colors"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-primary-600">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 0 1 2-2h4.586A2 2 0 0 1 12 2.586L15.414 6A2 2 0 0 1 16 7.414V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm2 6a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H7a1 1 0 0 1-1-1Zm1 3a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H7Z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="flex-1 font-medium">
                                                {{ $doc->media->name ?: $doc->media->file_name }}
                                            </span>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0 text-neutral-400">
                                                <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"/>
                                                <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/>
                                            </svg>
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
