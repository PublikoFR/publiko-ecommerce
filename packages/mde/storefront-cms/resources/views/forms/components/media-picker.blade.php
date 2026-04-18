@php
    use Spatie\MediaLibrary\MediaCollections\Models\Media;

    $statePath = $getStatePath();
    $state = $getState();
    $isMultiple = $isMultiple();
    $mediagroup = $getMediagroup();

    $ids = $isMultiple
        ? array_values(array_filter(array_map('intval', (array) $state)))
        : ($state ? [(int) $state] : []);

    $medias = empty($ids)
        ? collect()
        : Media::query()->whereIn('id', $ids)->get()->keyBy('id');
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="mdeMediaPickerField({
            statePath: @js($statePath),
            multiple: @js($isMultiple),
            mediagroup: @js($mediagroup),
            initialIds: @js($ids),
        })"
        x-init="init()"
        class="mpicker-field"
        wire:ignore.self
    >
        {{-- Single mode --}}
        @if (! $isMultiple)
            <template x-if="selected.length === 0">
                <button type="button" @click="open()" class="mpicker-field-empty">
                    <x-filament::icon icon="heroicon-o-photo" class="w-6 h-6 text-gray-400" />
                    <span>Choisir une image</span>
                </button>
            </template>
            <template x-if="selected.length > 0">
                <div class="mpicker-field-single">
                    <template x-for="m in selected" :key="m.id">
                        <div class="mpicker-field-thumb">
                            <template x-if="m.url">
                                <img :src="m.url" :alt="m.alt || ''" />
                            </template>
                            <template x-if="!m.url">
                                <div class="mpicker-field-fallback">
                                    <x-filament::icon icon="heroicon-o-document" class="w-8 h-8" />
                                </div>
                            </template>
                            <div class="mpicker-field-actions">
                                <button type="button" @click="open()" class="mpicker-field-action">
                                    <x-filament::icon icon="heroicon-o-arrow-path" class="w-4 h-4" />
                                    Remplacer
                                </button>
                                <button type="button" @click="remove(m.id)" class="mpicker-field-action is-danger">
                                    <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        @else
            {{-- Multi mode --}}
            <div class="mpicker-field-gallery">
                <template x-for="m in selected" :key="m.id">
                    <div class="mpicker-field-thumb is-multi">
                        <template x-if="m.url">
                            <img :src="m.url" :alt="m.alt || ''" />
                        </template>
                        <template x-if="!m.url">
                            <div class="mpicker-field-fallback">
                                <x-filament::icon icon="heroicon-o-document" class="w-6 h-6" />
                            </div>
                        </template>
                        <button type="button" @click="remove(m.id)" class="mpicker-field-remove" title="Retirer">
                            <x-filament::icon icon="heroicon-s-x-mark" class="w-3 h-3" />
                        </button>
                    </div>
                </template>
                <button type="button" @click="open()" class="mpicker-field-add">
                    <x-filament::icon icon="heroicon-o-plus" class="w-6 h-6" />
                    <span class="text-xs">Ajouter</span>
                </button>
            </div>
        @endif
    </div>

    {{-- Preload thumbnail metadata from server-rendered PHP --}}
    <script>
        (function () {
            window.__mdeMediaPickerMeta = window.__mdeMediaPickerMeta || {};
            @foreach ($medias as $media)
                window.__mdeMediaPickerMeta[{{ $media->id }}] = {
                    id: {{ $media->id }},
                    url: @js($media->getUrl()),
                    alt: @js((string) ($media->getCustomProperty('alt') ?? '')),
                    fileName: @js($media->file_name),
                };
            @endforeach
        })();
    </script>
</x-dynamic-component>

@once
    @push('scripts')
        <script>
            window.mdeMediaPickerField = function (config) {
                return {
                    statePath: config.statePath,
                    multiple: config.multiple,
                    mediagroup: config.mediagroup,
                    selected: [],

                    init() {
                        this.hydrate(config.initialIds || []);

                        // Listen to picker modal confirmations (global Livewire event).
                        Livewire.on('media-picked', (payload) => {
                            const data = Array.isArray(payload) ? payload[0] : payload;
                            if (!data || data.statePath !== this.statePath) return;
                            // Merge fresh medias meta into the cache.
                            const meta = window.__mdeMediaPickerMeta = window.__mdeMediaPickerMeta || {};
                            (data.medias || []).forEach(m => { meta[m.id] = m; });
                            this.hydrate(data.ids || []);
                            this.pushState();
                        });
                    },

                    hydrate(ids) {
                        const meta = window.__mdeMediaPickerMeta || {};
                        this.selected = ids.map(id => meta[id] || { id, url: null, alt: '', fileName: '#' + id });
                        this.fetchMissingMeta(ids.filter(id => !meta[id]));
                    },

                    async fetchMissingMeta(ids) {
                        if (!ids.length) return;
                        // Best-effort: reload only if we have an endpoint. Skip silently otherwise.
                        // Thumbnails already injected by PHP on first render cover most cases.
                    },

                    open() {
                        const ids = this.selected.map(m => m.id);
                        Livewire.dispatch('open-media-picker-modal', {
                            statePath: this.statePath,
                            multiple: this.multiple,
                            preselected: ids,
                            mediagroup: this.mediagroup,
                        });
                    },

                    remove(id) {
                        this.selected = this.selected.filter(m => m.id !== id);
                        this.pushState();
                    },

                    pushState() {
                        const ids = this.selected.map(m => m.id);
                        const value = this.multiple ? ids : (ids[0] ?? null);
                        const wireEl = this.$root.closest('[wire\\:id]');
                        const wireId = wireEl?.getAttribute('wire:id');
                        if (wireId && window.Livewire) {
                            window.Livewire.find(wireId)?.set(this.statePath, value, false);
                        }
                    },
                };
            };
        </script>
    @endpush
@endonce
