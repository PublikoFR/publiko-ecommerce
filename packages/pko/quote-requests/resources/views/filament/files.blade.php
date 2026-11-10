@php
    $files = $getRecord()->files->sortBy('id')->values();
    $photos = $files->filter->isPreviewable()->values();
    $others = $files->reject->isPreviewable()->values();

    // Données de la lightbox : noms de fichiers saisis par un tiers → @js
    // (JSON échappé pour l'attribut HTML), affichés via x-text uniquement.
    $slides = $photos->map(fn ($file): array => [
        'src' => $file->previewUrl(),
        'name' => $file->original_name,
        'size' => $file->humanSize(),
        'download' => $file->downloadUrl(),
    ])->all();
@endphp

@if ($files->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">Aucune pièce jointe.</p>
@else
    <div
        x-data="{
            open: false,
            index: 0,
            slides: @js($slides),
            show(i) { this.index = i; this.open = true },
            next() { this.index = (this.index + 1) % this.slides.length },
            prev() { this.index = (this.index - 1 + this.slides.length) % this.slides.length },
        }"
        x-on:keydown.escape.window="open = false"
        x-on:keydown.arrow-right.window="open && next()"
        x-on:keydown.arrow-left.window="open && prev()"
        class="space-y-4"
    >
        @if ($photos->isNotEmpty())
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($photos as $i => $photo)
                    <li>
                        <button
                            type="button"
                            x-on:click="show({{ $i }})"
                            class="group relative block aspect-square w-full overflow-hidden rounded-lg border border-gray-200 bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                            aria-label="Agrandir {{ $photo->original_name }}"
                        >
                            <img
                                src="{{ $photo->previewUrl() }}"
                                alt="{{ $photo->original_name }}"
                                loading="lazy"
                                class="h-full w-full object-cover transition duration-200 group-hover:scale-105"
                            />
                            <span class="pointer-events-none absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/70 to-transparent px-2 pb-1.5 pt-6 text-left text-xs font-medium text-white">
                                {{ $photo->original_name }}
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($others->isNotEmpty())
            <ul class="grid gap-2 sm:grid-cols-2">
                @foreach ($others as $file)
                    <li class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5 text-sm dark:border-white/10">
                        <x-filament::icon icon="heroicon-o-document" class="h-6 w-6 shrink-0 text-gray-400" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-950 dark:text-white">{{ $file->original_name }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $file->humanSize() }}</span>
                        </span>
                        <x-filament::icon-button :href="$file->downloadUrl()" tag="a" icon="heroicon-o-arrow-down-tray" label="Télécharger {{ $file->original_name }}" />
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($photos->isNotEmpty())
            <template x-teleport="body">
                <div
                    x-show="open"
                    x-transition.opacity
                    x-on:click.self="open = false"
                    class="fixed inset-0 z-[60] flex flex-col items-center justify-center gap-4 bg-black/85 p-4 sm:p-10"
                    role="dialog"
                    aria-modal="true"
                    style="display: none"
                >
                    <button type="button" x-on:click="open = false" class="absolute right-4 top-4 rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white" aria-label="Fermer">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-6 w-6" />
                    </button>

                    <template x-if="slides.length > 1">
                        <div>
                            <button type="button" x-on:click="prev()" class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white sm:left-4" aria-label="Photo précédente">
                                <x-filament::icon icon="heroicon-o-chevron-left" class="h-8 w-8" />
                            </button>
                            <button type="button" x-on:click="next()" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white sm:right-4" aria-label="Photo suivante">
                                <x-filament::icon icon="heroicon-o-chevron-right" class="h-8 w-8" />
                            </button>
                        </div>
                    </template>

                    <img :src="slides[index].src" :alt="slides[index].name" class="max-h-[80vh] max-w-full rounded-lg object-contain shadow-2xl" />

                    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm text-white/90">
                        <span class="font-medium" x-text="slides[index].name"></span>
                        <span class="text-white/60" x-text="slides[index].size"></span>
                        <span class="text-white/60" x-show="slides.length > 1" x-text="(index + 1) + ' / ' + slides.length"></span>
                        <a :href="slides[index].download" class="inline-flex items-center gap-1 font-medium text-white underline-offset-4 hover:underline">
                            <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                            Télécharger l'original
                        </a>
                    </div>
                </div>
            </template>
        @endif
    </div>
@endif
