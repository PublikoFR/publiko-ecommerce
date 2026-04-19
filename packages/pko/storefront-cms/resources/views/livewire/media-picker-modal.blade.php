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
                            <button
                                type="button"
                                x-on:click="urlImport = !urlImport"
                                class="mpicker-upload-btn"
                                :class="{ 'is-dragover': urlImport }"
                            >
                                <x-filament::icon icon="heroicon-o-link" class="w-4 h-4" />
                                Importer via URL
                            </button>
                        </div>

                        <div x-show="urlImport" x-cloak class="flex flex-wrap items-start gap-2 px-2 py-2 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                            <div class="flex-1 min-w-[240px]">
                                <input
                                    type="url"
                                    wire:model="importUrl"
                                    placeholder="https://exemple.com/image.jpg"
                                    class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                                />
                            </div>
                            <div class="flex-1 min-w-[180px]">
                                <input
                                    type="text"
                                    wire:model="importName"
                                    placeholder="Nom (optionnel)"
                                    class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                                />
                            </div>
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

                    {{-- Volet latéral détails / édition — ouvre au clic sur une tuile --}}
                    @if ($this->focusedMedia)
                        @php $fm = $this->focusedMedia; @endphp
                        <aside class="mpicker-details">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="mpicker-sidebar-title m-0">Détails</h3>
                                <button type="button" wire:click="clearFocus" class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-white">
                                    <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
                                </button>
                            </div>

                            @if (str_starts_with((string) $fm->mime_type, 'image/'))
                                <div class="bg-gray-100 dark:bg-white/5 rounded-lg overflow-hidden flex items-center justify-center p-2 mb-3" style="aspect-ratio:1/1;">
                                    <img src="{{ $fm->getUrl() }}" alt="" class="max-w-full max-h-full object-contain" />
                                </div>
                            @else
                                <div class="bg-gray-100 dark:bg-white/5 rounded-lg flex items-center justify-center p-6 mb-3">
                                    <x-filament::icon icon="heroicon-o-document" class="w-12 h-12 text-gray-400" />
                                </div>
                            @endif

                            <dl class="grid grid-cols-2 gap-2 text-[11px] text-gray-600 dark:text-gray-400 mb-3">
                                <div class="bg-gray-50 dark:bg-white/5 rounded p-2">
                                    <dt class="font-semibold uppercase tracking-wider" style="font-size:0.6rem;">Fichier</dt>
                                    <dd class="text-gray-900 dark:text-gray-100 break-all m-0">{{ $fm->file_name }}</dd>
                                </div>
                                <div class="bg-gray-50 dark:bg-white/5 rounded p-2">
                                    <dt class="font-semibold uppercase tracking-wider" style="font-size:0.6rem;">Type</dt>
                                    <dd class="text-gray-900 dark:text-gray-100 m-0">{{ $fm->mime_type }}</dd>
                                </div>
                                <div class="bg-gray-50 dark:bg-white/5 rounded p-2">
                                    <dt class="font-semibold uppercase tracking-wider" style="font-size:0.6rem;">Taille</dt>
                                    <dd class="text-gray-900 dark:text-gray-100 m-0">{{ number_format($fm->size / 1024, 1) }} Ko</dd>
                                </div>
                                <div class="bg-gray-50 dark:bg-white/5 rounded p-2">
                                    <dt class="font-semibold uppercase tracking-wider" style="font-size:0.6rem;">Uploadé</dt>
                                    <dd class="text-gray-900 dark:text-gray-100 m-0">{{ $fm->created_at?->format('d/m/Y') }}</dd>
                                </div>
                            </dl>

                            <div class="flex flex-col gap-3">
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Nom du fichier <span class="text-gray-400">(sans extension)</span></label>
                                    <input type="text" wire:model="editName" class="w-full text-xs border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-mono" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Titre</label>
                                    <input type="text" wire:model="editTitle" class="w-full text-xs border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-950 text-gray-900 dark:text-white" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Alt <span class="text-gray-400">(accessibilité/SEO)</span></label>
                                    <textarea wire:model="editAlt" rows="3" class="w-full text-xs border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-950 text-gray-900 dark:text-white"></textarea>
                                </div>
                                <x-filament::button size="sm" wire:click="saveFocusedMeta" wire:loading.attr="disabled" wire:target="saveFocusedMeta">
                                    <span wire:loading.remove wire:target="saveFocusedMeta">Enregistrer les méta</span>
                                    <span wire:loading wire:target="saveFocusedMeta">Enregistrement…</span>
                                </x-filament::button>
                            </div>
                        </aside>
                    @endif
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
                urlImport: false,
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
