<div>
    @if ($offers->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach ($offers as $offer)
                <article class="bg-white border border-neutral-200 rounded-lg overflow-hidden hover:shadow-md transition">
                    @if ($offer->image_url)
                        <div class="relative aspect-[16/9]">
                            <img src="{{ $offer->image_url }}" alt="" class="w-full h-full object-cover" />
                            @if ($offer->badge)
                                <span class="absolute top-2 left-2"><x-ui.badge variant="warning">{{ $offer->badge }}</x-ui.badge></span>
                            @endif
                        </div>
                    @endif
                    <div class="p-4">
                        <h3 class="font-bold text-neutral-900">{{ $offer->title }}</h3>
                        @if ($offer->subtitle)<p class="text-sm text-neutral-600 mt-1">{{ $offer->subtitle }}</p>@endif
                        @if ($offer->cta_label && $offer->cta_url)
                            <x-ui.button variant="outline" size="sm" :href="$offer->cta_url" class="mt-3 w-full justify-center">{{ $offer->cta_label }}</x-ui.button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
