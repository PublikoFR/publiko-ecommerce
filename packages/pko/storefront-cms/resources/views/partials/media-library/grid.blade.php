{{--
    Zone centrale : titre dossier + dropzone + import URL + grille + bulk bar.
    Markup unifié page/modal. Les capacités sont portées par des flags.

    Variables attendues :
    - $currentFolder : ?Folder
    - $medias : Collection<Media>
    - $selectedMediaIds : int[]
    - $canDeleteFolder : bool
    - $canBulkManage : bool
    - $tileBodyAction : 'openMedia' | 'toggleMediaSelection'
      (page = openMedia → ouvre le drawer sans sélectionner ; modal = toggleMediaSelection →
       sélection auto + ouverture drawer côté serveur)
--}}
<div class="mlib-main">
    @if ($currentFolder === null)
        <div class="mlib-empty-folder">
            <x-filament::icon icon="heroicon-o-folder-open" class="w-16 h-16 mx-auto text-gray-300" />
            <p class="mt-4 font-semibold text-gray-700 dark:text-gray-300">Sélectionnez un dossier</p>
            <p class="text-sm text-gray-500 mt-1">Clique sur un dossier dans la barre latérale pour voir ses médias.</p>
        </div>
    @else
        {{-- Entête dossier --}}
        <div class="mlib-main-header">
            <div class="flex items-center gap-2 min-w-0">
                <x-filament::icon icon="heroicon-s-folder" class="w-5 h-5 text-primary-600 shrink-0" />
                <h2 class="font-bold text-base text-gray-900 dark:text-white m-0 truncate">{{ $currentFolder->name }}</h2>
                <span class="text-sm font-normal text-gray-500 shrink-0">({{ $medias->count() }})</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <input
                    type="text"
                    wire:model.live.debounce.250ms="search"
                    placeholder="Rechercher…"
                    class="mlib-search"
                />
                @if ($canDeleteFolder ?? false)
                    <button
                        type="button"
                        wire:click="deleteFolder({{ $currentFolder->id }})"
                        wire:confirm="Supprimer le dossier « {{ $currentFolder->name }} » ?"
                        class="mlib-folder-delete"
                        title="Supprimer ce dossier"
                    >
                        <x-filament::icon icon="heroicon-o-trash" class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>

        {{-- Dropzone --}}
        <div
            class="mlib-dropzone"
            :class="{ 'is-dragover': dragover }"
            @dragover.prevent="dragover = true"
            @dragleave.prevent="dragover = false"
            @drop.prevent="dragover = false; handleFiles($event.dataTransfer.files)"
            x-data="{ triggerBrowse() { this.$refs.fileInput.click(); } }"
        >
            <input
                x-ref="fileInput"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,application/pdf,video/mp4"
                @change="handleFiles($event.target.files); $event.target.value = ''"
                class="sr-only"
            />
            <x-filament::icon icon="heroicon-o-cloud-arrow-up" class="w-8 h-8 text-primary-400" />
            <p class="mlib-dropzone-text">Glisse des fichiers ici pour importer</p>
            <p class="mlib-dropzone-or">— ou —</p>
            <div class="mlib-dropzone-actions">
                <button type="button" class="mlib-dropzone-btn is-primary" x-on:click="triggerBrowse()">
                    <x-filament::icon icon="heroicon-o-folder-open" class="w-4 h-4" />
                    Parcourir
                </button>
                <button type="button" class="mlib-dropzone-btn" x-on:click="urlImport = !urlImport" :class="{ 'is-active': urlImport }">
                    <x-filament::icon icon="heroicon-o-link" class="w-4 h-4" />
                    Coller une URL
                </button>
            </div>
            <p class="mlib-dropzone-hint">JPG, PNG, WEBP, GIF, SVG, PDF, MP4 · max 20 Mo</p>
        </div>

        {{-- Import URL (toggle) --}}
        <div x-show="urlImport" x-cloak class="mlib-url-form">
            <input
                type="url"
                wire:model="importUrl"
                placeholder="https://exemple.com/image.jpg"
                class="mlib-input flex-1 min-w-[240px]"
            />
            <input
                type="text"
                wire:model="importName"
                placeholder="Nom (optionnel)"
                class="mlib-input flex-1 min-w-[180px]"
            />
            <x-filament::button
                color="primary"
                size="sm"
                wire:click="importFromUrl"
                wire:loading.attr="disabled"
                wire:target="importFromUrl"
            >
                <span wire:loading.remove wire:target="importFromUrl">Importer</span>
                <span wire:loading wire:target="importFromUrl">Import…</span>
            </x-filament::button>
            @if ($importError)
                <p class="w-full text-xs text-danger-600">{{ $importError }}</p>
            @endif
        </div>

        {{-- Bulk bar : uniquement en mode page --}}
        @if (($canBulkManage ?? false) && ! empty($selectedMediaIds))
            <div class="mlib-bulkbar">
                <div class="flex items-center gap-2">
                    <span class="mlib-bulkbar-count">{{ count($selectedMediaIds) }}</span>
                    <span class="text-sm font-medium">sélectionné{{ count($selectedMediaIds) > 1 ? 's' : '' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="selectAllMedias" class="mlib-bulkbar-btn">Tout cocher</button>
                    <button type="button" wire:click="clearMediaSelection" class="mlib-bulkbar-btn">Annuler</button>
                    <button type="button" wire:click="openBulkMoveModal" class="mlib-bulkbar-btn is-primary">
                        <x-filament::icon icon="heroicon-o-arrow-right-circle" class="w-4 h-4" />
                        Déplacer
                    </button>
                    <button type="button" x-on:click="if (confirm('Supprimer {{ count($selectedMediaIds) }} média(s) ?')) $wire.bulkDeleteMedias();" class="mlib-bulkbar-btn is-danger">
                        <x-filament::icon icon="heroicon-o-trash" class="w-4 h-4" />
                        Supprimer
                    </button>
                </div>
            </div>
        @endif

        {{-- Grille --}}
        @if ($medias->isEmpty())
            <div x-show="pending.length === 0" class="mlib-empty-grid">
                <p>Aucun média dans ce dossier. Upload via la zone ci-dessus.</p>
            </div>
        @endif

        <div class="mlib-grid" x-show="pending.length > 0 || {{ $medias->count() }} > 0">
            {{-- Tuiles optimistes --}}
            <template x-for="item in pending" :key="item.id">
                <div class="mlib-tile is-uploading">
                    <div class="mlib-tile-body" style="cursor:default;">
                        <template x-if="item.previewUrl">
                            <img :src="item.previewUrl" alt="" />
                        </template>
                        <template x-if="!item.previewUrl">
                            <div class="absolute inset-0 flex items-center justify-center text-gray-300">
                                <x-filament::icon icon="heroicon-o-document" class="w-10 h-10" />
                            </div>
                        </template>
                        <div class="mlib-tile-uploading-overlay" :class="{ 'has-error': item.error }">
                            <template x-if="!item.error">
                                <div class="mlib-tile-spinner">
                                    <svg viewBox="0 0 36 36" width="36" height="36">
                                        <circle cx="18" cy="18" r="15" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="3" />
                                        <circle cx="18" cy="18" r="15" fill="none" stroke="#fff" stroke-width="3"
                                                stroke-dasharray="94.25" :stroke-dashoffset="94.25 - (94.25 * item.progress / 100)"
                                                stroke-linecap="round" transform="rotate(-90 18 18)"
                                                style="transition: stroke-dashoffset 120ms linear;" />
                                    </svg>
                                    <span class="mlib-tile-progress" x-text="item.progress + '%'"></span>
                                </div>
                            </template>
                            <template x-if="item.error">
                                <div class="text-white text-xs font-semibold text-center px-2">
                                    <x-filament::icon icon="heroicon-s-exclamation-triangle" class="w-6 h-6 mx-auto mb-1" />
                                    Échec
                                </div>
                            </template>
                        </div>
                        <div class="mlib-tile-caption" x-text="item.name"></div>
                    </div>
                </div>
            </template>

            @foreach ($medias as $media)
                @php $isSelected = in_array($media->id, $selectedMediaIds, true); @endphp
                <div class="mlib-tile {{ $isSelected ? 'is-selected' : '' }}" wire:key="media-{{ $media->id }}">
                    <button
                        type="button"
                        wire:click.stop="toggleMediaSelection({{ $media->id }})"
                        class="mlib-tile-checkbox {{ $isSelected ? 'is-checked' : '' }}"
                        title="Cocher"
                    >
                        @if ($isSelected)
                            <x-filament::icon icon="heroicon-s-check" class="w-4 h-4" />
                        @endif
                    </button>

                    <button type="button" wire:click="{{ $tileBodyAction ?? 'openMedia' }}({{ $media->id }})" class="mlib-tile-body">
                        @if (str_starts_with((string) $media->mime_type, 'image/'))
                            <img src="{{ $media->getUrl() }}" alt="{{ $media->getCustomProperty('alt') ?? '' }}" loading="lazy" />
                        @else
                            <div class="absolute inset-0 flex items-center justify-center text-gray-300">
                                <x-filament::icon icon="heroicon-o-document" class="w-10 h-10" />
                            </div>
                        @endif
                        <div class="mlib-tile-caption">{{ $media->file_name }}</div>
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
