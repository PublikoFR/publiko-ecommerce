<div
    x-data="{}"
    x-on:media-picker-opened.window="document.body.style.overflow = 'hidden'"
    x-on:media-picker-closed.window="document.body.style.overflow = ''"
    x-on:media-picked.window="document.body.style.overflow = ''"
>
    @if ($open)
        <div
            x-data="mdeMediaPicker()"
            x-on:keydown.escape.window="$wire.closeModal()"
            class="mpicker-backdrop"
        >
            <div wire:click="closeModal" class="absolute inset-0"></div>

            <div class="mpicker-box">
                <header class="mpicker-header">
                    <h2>
                        @if ($multiple)
                            Sélectionner des médias
                        @else
                            Sélectionner un média
                        @endif
                        <span class="mpicker-group-badge">{{ $mediagroup }}</span>
                    </h2>
                    <button type="button" wire:click="closeModal" class="mpicker-close">
                        <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                    </button>
                </header>

                <div class="mpicker-body">
                    {{-- Sidebar dossiers --}}
                    <aside class="mpicker-sidebar">
                        <h3 class="mpicker-sidebar-title">Dossiers</h3>
                        @if ($this->folders->isEmpty())
                            <p class="text-xs text-gray-500 italic">Aucun dossier. Crée-en un depuis la médiathèque.</p>
                        @else
                            <ul class="list-none p-0 m-0 flex flex-col gap-1">
                                @foreach ($this->folders as $folder)
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="selectFolder({{ $folder->id }})"
                                            class="mpicker-folder-btn {{ $currentFolderId === $folder->id ? 'is-active' : '' }}"
                                        >
                                            <x-filament::icon icon="heroicon-o-folder" class="w-4 h-4 shrink-0" />
                                            <span class="flex-1 truncate">{{ $folder->name }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </aside>

                    {{-- Main grid --}}
                    <div class="mpicker-main">
                        <div class="mpicker-toolbar">
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="search"
                                placeholder="Rechercher par nom de fichier…"
                                class="mpicker-search"
                            />
                            <label class="mpicker-upload-btn" :class="{ 'is-dragover': dragover }"
                                @dragover.prevent="dragover = true"
                                @dragleave.prevent="dragover = false"
                                @drop.prevent="dragover = false; handleFiles($event.dataTransfer.files)">
                                <input type="file"
                                       accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,application/pdf,video/mp4"
                                       @change="handleFiles($event.target.files); $event.target.value = ''"
                                       class="sr-only"
                                       {{ $multiple ? 'multiple' : '' }} />
                                <x-filament::icon icon="heroicon-o-arrow-up-tray" class="w-4 h-4" />
                                Uploader
                            </label>
                        </div>

                        @if ($currentFolderId === null)
                            <div class="mpicker-empty">
                                <x-filament::icon icon="heroicon-o-folder-open" class="w-12 h-12 text-gray-300 mx-auto" />
                                <p class="mt-3 text-sm text-gray-500">Sélectionne un dossier à gauche.</p>
                            </div>
                        @elseif ($this->medias->isEmpty() && empty($pending ?? []))
                            <div class="mpicker-empty">
                                <p class="text-sm text-gray-500">Aucun média dans ce dossier.</p>
                            </div>
                        @else
                            <div class="mpicker-grid">
                                {{-- Tuiles optimistes upload --}}
                                <template x-for="item in pending" :key="item.id">
                                    <div class="mpicker-tile is-uploading">
                                        <template x-if="item.previewUrl">
                                            <img :src="item.previewUrl" alt="" />
                                        </template>
                                        <div class="mpicker-tile-overlay" :class="{ 'has-error': item.error }">
                                            <template x-if="!item.error">
                                                <div class="mpicker-tile-spinner">
                                                    <svg viewBox="0 0 36 36" width="32" height="32">
                                                        <circle cx="18" cy="18" r="15" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="3" />
                                                        <circle cx="18" cy="18" r="15" fill="none" stroke="#fff" stroke-width="3"
                                                                stroke-dasharray="94.25" :stroke-dashoffset="94.25 - (94.25 * item.progress / 100)"
                                                                stroke-linecap="round" transform="rotate(-90 18 18)"
                                                                style="transition: stroke-dashoffset 120ms linear;" />
                                                    </svg>
                                                </div>
                                            </template>
                                            <template x-if="item.error">
                                                <div class="text-white text-xs">Échec</div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                @foreach ($this->medias as $media)
                                    @php $isSelected = in_array($media->id, $selected, true); @endphp
                                    <button
                                        type="button"
                                        wire:click="toggle({{ $media->id }})"
                                        class="mpicker-tile {{ $isSelected ? 'is-selected' : '' }}"
                                        wire:key="mp-media-{{ $media->id }}"
                                        title="{{ $media->file_name }}"
                                    >
                                        @if (str_starts_with((string) $media->mime_type, 'image/'))
                                            <img src="{{ $media->getUrl() }}" alt="" loading="lazy" />
                                        @else
                                            <div class="absolute inset-0 flex items-center justify-center text-gray-300">
                                                <x-filament::icon icon="heroicon-o-document" class="w-10 h-10" />
                                            </div>
                                        @endif
                                        @if ($isSelected)
                                            <span class="mpicker-tile-check">
                                                <x-filament::icon icon="heroicon-s-check" class="w-4 h-4" />
                                            </span>
                                        @endif
                                        <div class="mpicker-tile-caption">{{ $media->file_name }}</div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <footer class="mpicker-footer">
                    <span class="text-sm text-gray-500">
                        @if ($multiple)
                            {{ count($selected) }} sélectionné{{ count($selected) > 1 ? 's' : '' }}
                        @else
                            {{ ! empty($selected) ? '1 sélectionné' : 'Aucune sélection' }}
                        @endif
                    </span>
                    <div class="flex items-center gap-2">
                        <x-filament::button type="button" color="gray" wire:click="closeModal">Annuler</x-filament::button>
                        <x-filament::button
                            type="button"
                            wire:click="confirm"
                            :disabled="empty($selected)"
                        >
                            Confirmer
                        </x-filament::button>
                    </div>
                </footer>
            </div>
        </div>
    @endif

    @script
    <script>
        window.mdeMediaPicker = function () {
            return {
                dragover: false,
                pending: [],
                handleFiles(fileList) {
                    const files = Array.from(fileList || []);
                    for (const file of files) this.enqueue(file);
                },
                enqueue(file) {
                    const id = 'mpu-' + Math.random().toString(36).slice(2, 10);
                    const isImage = file.type.startsWith('image/');
                    const item = { id, name: file.name, previewUrl: isImage ? URL.createObjectURL(file) : null, progress: 0, error: false };
                    this.pending.push(item);
                    this.$wire.upload(
                        'pendingUpload',
                        file,
                        async () => {
                            try { await this.$wire.persistPendingUpload(file.name); }
                            catch (e) { console.error(e); item.error = true; return; }
                            this.remove(item);
                        },
                        (err) => { console.error('Upload error', err); item.error = true; },
                        (event) => { item.progress = event.detail.progress; }
                    );
                },
                remove(item) {
                    if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
                    this.pending = this.pending.filter(p => p.id !== item.id);
                },
            };
        };
    </script>
    @endscript
</div>
