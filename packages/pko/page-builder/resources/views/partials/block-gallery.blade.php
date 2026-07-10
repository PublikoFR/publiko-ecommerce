@php
    $ids = is_array($block['media_ids'] ?? null) ? $block['media_ids'] : [];
    $cols = in_array((int) ($block['columns'] ?? 3), [2, 3, 4], true) ? (int) $block['columns'] : 3;
    $urls = [];
    if (! empty($ids) && class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class)) {
        $medias = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            if ($m = $medias->get($id)) {
                $urls[] = $m->getFullUrl();
            }
        }
    }
    $grid = match ($cols) {
        2 => 'grid-cols-2',
        4 => 'grid-cols-2 md:grid-cols-4',
        default => 'grid-cols-2 md:grid-cols-3',
    };
@endphp
@if (! empty($urls))
    <div class="pko-pb-block pko-pb-block--gallery my-3"
         x-data="{ open: false, i: 0, imgs: @js($urls),
                   show(n) { this.i = n; this.open = true; },
                   next() { this.i = (this.i + 1) % this.imgs.length; },
                   prev() { this.i = (this.i - 1 + this.imgs.length) % this.imgs.length; } }">
        <div class="grid gap-3 {{ $grid }}">
            @foreach ($urls as $idx => $u)
                <button type="button" @click="show({{ $idx }})"
                        class="group relative block overflow-hidden rounded-lg focus:outline-none focus:ring-2 focus:ring-accent-500"
                        style="aspect-ratio:1/1" aria-label="Agrandir l'image {{ $idx + 1 }}">
                    <img src="{{ $u }}" alt="" loading="lazy"
                         class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" />
                    <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/0 text-white opacity-0 transition group-hover:bg-black/25 group-hover:opacity-100">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Lightbox --}}
        <template x-teleport="body">
            <div x-show="open" x-cloak style="display:none"
                 x-transition.opacity
                 @keydown.escape.window="open = false"
                 @keydown.arrow-right.window="open && next()"
                 @keydown.arrow-left.window="open && prev()"
                 class="fixed inset-0 z-[100] flex items-center justify-center bg-black/85"
                 @click.self="open = false">
                <button type="button" @click="open = false" aria-label="Fermer"
                        class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>

                <button type="button" x-show="imgs.length > 1" @click="prev()" aria-label="Précédente"
                        class="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><path d="M15 18l-6-6 6-6"/></svg>
                </button>

                <img :src="imgs[i]" alt="" class="max-h-[90vh] max-w-[92vw] rounded-lg object-contain shadow-2xl" @click.stop />

                <button type="button" x-show="imgs.length > 1" @click="next()" aria-label="Suivante"
                        class="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><path d="M9 18l6-6-6-6"/></svg>
                </button>

                <div x-show="imgs.length > 1" class="absolute bottom-5 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium text-white" x-text="(i + 1) + ' / ' + imgs.length"></div>
            </div>
        </template>
    </div>
@endif
