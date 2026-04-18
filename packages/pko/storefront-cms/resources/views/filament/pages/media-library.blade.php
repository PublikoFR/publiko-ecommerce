<x-filament-panels::page>
    <div x-data class="mlib-layout">
        {{-- Sidebar dossiers --}}
        <aside class="mlib-sidebar">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-xs uppercase tracking-wider text-gray-700 dark:text-gray-300 m-0">Dossiers</h3>
                <button type="button" wire:click="$set('showCreateFolderModal', true)" class="text-xs font-semibold text-primary-600 hover:text-primary-700">+ Nouveau</button>
            </div>

            @if (empty($this->foldersTree))
                <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-6">Aucun dossier.<br>Cliquez « + Nouveau ».</p>
            @else
                <ul class="list-none p-0 m-0 flex flex-col gap-1">
                    @foreach ($this->foldersTree as $folder)
                        <li>
                            <button type="button" wire:click="selectFolder({{ $folder['id'] }})" class="mlib-folder-btn w-full {{ $currentFolderId === $folder['id'] ? 'is-active' : '' }}">
                                <x-filament::icon icon="heroicon-o-folder" class="w-4 h-4 shrink-0" />
                                <span class="flex-1 truncate">{{ $folder['name'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>

        {{-- Main --}}
        <div class="min-w-0 flex flex-col gap-6">
            @if ($currentFolderId === null)
                <div class="mlib-card text-center" style="padding:4rem 1.5rem;">
                    <x-filament::icon icon="heroicon-o-folder-open" class="w-16 h-16 mx-auto text-gray-300" />
                    <p class="mt-4 font-semibold text-gray-700 dark:text-gray-300">Sélectionnez un dossier</p>
                    <p class="text-sm text-gray-500 mt-1">Cliquez sur un dossier dans la barre latérale pour voir ses médias.</p>
                </div>
            @else
                {{-- Card unique : titre + dropzone + médias --}}
                <div
                    class="mlib-card"
                    x-data="mlibUploader()"
                >
                    <div class="flex items-center justify-between mb-5">
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-s-folder" class="w-5 h-5 text-primary-600" />
                            <h2 class="font-bold text-lg text-gray-900 dark:text-white m-0">{{ $this->currentFolder?->name }}</h2>
                            <span class="text-sm font-normal text-gray-500">({{ $this->medias->count() }})</span>
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($this->medias->isNotEmpty() && empty($selectedMediaIds))
                                <button type="button" wire:click="selectAllMedias" class="text-xs font-semibold text-primary-600 hover:text-primary-700">Tout sélectionner</button>
                            @endif
                            <button
                                type="button"
                                wire:click="deleteFolder({{ $this->currentFolder?->id }})"
                                wire:confirm="Supprimer le dossier « {{ $this->currentFolder?->name }} » ?"
                                class="mlib-folder-delete"
                                title="Supprimer ce dossier"
                            >
                                <x-filament::icon icon="heroicon-o-trash" class="w-4 h-4" />
                                Supprimer le dossier
                            </button>
                        </div>
                    </div>

                    {{-- Dropzone custom --}}
                    <label
                        class="mlib-dropzone mb-5"
                        :class="{ 'is-dragover': dragover }"
                        @dragover.prevent="dragover = true"
                        @dragleave.prevent="dragover = false"
                        @drop.prevent="dragover = false; handleFiles($event.dataTransfer.files)"
                    >
                        <input
                            type="file"
                            multiple
                            accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,application/pdf,video/mp4"
                            @change="handleFiles($event.target.files); $event.target.value = ''"
                            class="sr-only"
                        />
                        <x-filament::icon icon="heroicon-o-cloud-arrow-up" class="w-10 h-10 text-primary-400" />
                        <p class="mlib-dropzone-text">
                            Faites glisser vos fichiers ici ou <span class="mlib-dropzone-link">parcourir</span>
                        </p>
                        <p class="mlib-dropzone-hint">JPG, PNG, WEBP, GIF, SVG, PDF, MP4 · max 20 Mo</p>
                    </label>

                    {{-- Barre d'actions bulk --}}
                    @if (! empty($selectedMediaIds))
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

                    @if ($this->medias->isEmpty())
                        <div x-show="pending.length === 0">
                            <p class="text-center text-gray-500 text-sm" style="padding:4rem 0;">Aucun média dans ce dossier. Uploadez des fichiers via la zone ci-dessus.</p>
                        </div>
                    @endif

                    <div class="mlib-grid" x-show="pending.length > 0 || {{ $this->medias->count() }} > 0">
                        {{-- Tuiles optimistes (upload en cours) --}}
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

                        @foreach ($this->medias as $media)
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

                                <button type="button" wire:click="openMedia({{ $media->id }})" class="mlib-tile-body">
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
                </div>
            @endif
        </div>
    </div>{{-- /.mlib-layout --}}

    {{-- Modal "Nouveau dossier" --}}
    @if ($showCreateFolderModal)
        <template x-teleport="body">
            <div
                x-data
                x-init="document.body.style.overflow='hidden'"
                x-on:keydown.escape.window="$wire.set('showCreateFolderModal', false)"
                x-on:remove.once="document.body.style.overflow=''"
                class="mlib-modal-backdrop"
            >
                <div wire:click="$set('showCreateFolderModal', false)" class="absolute inset-0"></div>
                <div class="mlib-modal-box">
                    <header class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-white/10">
                        <h2 class="font-bold text-lg text-gray-900 dark:text-white m-0">Nouveau dossier</h2>
                        <button type="button" wire:click="$set('showCreateFolderModal', false)" class="bg-transparent border-0 text-gray-400 hover:text-gray-600 cursor-pointer">
                            <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                        </button>
                    </header>
                    <form wire:submit="createFolder" class="p-6 flex flex-col gap-5">
                        <div>
                            <label class="mlib-label">Nom du dossier</label>
                            <input type="text" wire:model="newFolderName" required class="mlib-input" placeholder="Ex: Produits Milwaukee" autofocus />
                            @error('newFolderName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mlib-label">Collection Spatie</label>
                            <input type="text" wire:model="newFolderCollection" required class="mlib-input font-mono" placeholder="default" />
                            <p class="mt-1.5 text-xs text-gray-500">Identifiant technique (slug). Utilise <code class="bg-gray-100 dark:bg-white/10 px-1 rounded">default</code> sauf si tu sais ce que tu fais. Les médias Lunar sont sous <code class="bg-gray-100 dark:bg-white/10 px-1 rounded">products</code>.</p>
                            @error('newFolderCollection')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex justify-end gap-2 -mx-6 -mb-6 px-6 py-4 border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                            <x-filament::button type="button" color="gray" wire:click="$set('showCreateFolderModal', false)">Annuler</x-filament::button>
                            <x-filament::button type="submit">Créer</x-filament::button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    @endif

    {{-- Modal "Déplacer vers" --}}
    @if ($showBulkMoveModal)
        <template x-teleport="body">
            <div
                x-data
                x-init="document.body.style.overflow='hidden'"
                x-on:keydown.escape.window="$wire.set('showBulkMoveModal', false)"
                x-on:remove.once="document.body.style.overflow=''"
                class="mlib-modal-backdrop"
            >
                <div wire:click="$set('showBulkMoveModal', false)" class="absolute inset-0"></div>
                <div class="mlib-modal-box">
                    <header class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-white/10">
                        <h2 class="font-bold text-lg text-gray-900 dark:text-white m-0">Déplacer {{ count($selectedMediaIds) }} média{{ count($selectedMediaIds) > 1 ? 's' : '' }}</h2>
                        <button type="button" wire:click="$set('showBulkMoveModal', false)" class="bg-transparent border-0 text-gray-400 hover:text-gray-600 cursor-pointer">
                            <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                        </button>
                    </header>
                    <form wire:submit="confirmBulkMove" class="p-6 flex flex-col gap-5">
                        <div>
                            <label class="mlib-label">Dossier de destination</label>
                            @if ($this->otherFolders->isEmpty())
                                <p class="text-sm text-gray-500 italic">Aucun autre dossier disponible. Crée un dossier d'abord.</p>
                            @else
                                <select wire:model="bulkMoveTargetFolderId" required class="mlib-input">
                                    <option value="">— Choisir un dossier —</option>
                                    @foreach ($this->otherFolders as $folder)
                                        <option value="{{ $folder->id }}">{{ $folder->name }} ({{ $folder->collection }})</option>
                                    @endforeach
                                </select>
                                @error('bulkMoveTargetFolderId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                <p class="mt-1.5 text-xs text-gray-500">Les médias changeront de dossier et de collection Spatie.</p>
                            @endif
                        </div>
                        <div class="flex justify-end gap-2 -mx-6 -mb-6 px-6 py-4 border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                            <x-filament::button type="button" color="gray" wire:click="$set('showBulkMoveModal', false)">Annuler</x-filament::button>
                            @if ($this->otherFolders->isNotEmpty())
                                <x-filament::button type="submit">Déplacer</x-filament::button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </template>
    @endif

    {{-- Slide-over édition --}}
    @if ($this->selectedMedia)
        <template x-teleport="body">
            <div
                x-data="{
                    lightbox: false,
                    closing: false,
                    close() {
                        if (this.closing) return;
                        this.closing = true;
                        setTimeout(() => $wire.closeMedia(), 260);
                    },
                }"
                x-on:keydown.escape.window="!lightbox && close()"
                :class="{ 'is-closing': closing }"
                class="mlib-slideover-wrap"
            >
                <div class="mlib-slideover-backdrop" @click="close()"></div>
                <aside class="mlib-slideover-panel">
                    <header class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-white/10 shrink-0">
                        <h2 class="font-bold text-gray-900 dark:text-white m-0">Édition média</h2>
                        <button type="button" @click="close()" class="bg-transparent border-0 text-gray-400 hover:text-gray-600 cursor-pointer">
                            <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                        </button>
                    </header>

                    <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6">
                        @if (str_starts_with((string) $this->selectedMedia->mime_type, 'image/'))
                            <button type="button" @click="lightbox = true" class="mlib-preview-btn bg-gray-100 dark:bg-white/5 rounded-lg overflow-hidden flex items-center justify-center p-3 border-0 cursor-zoom-in" style="aspect-ratio:16/9;">
                                <img src="{{ $this->selectedMedia->getUrl() }}" alt="" class="max-w-full max-h-full object-contain" />
                            </button>
                        @endif

                        <dl class="grid grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-400 m-0">
                            @foreach ([
                                'Fichier' => $this->selectedMedia->file_name,
                                'Type' => $this->selectedMedia->mime_type,
                                'Taille' => number_format($this->selectedMedia->size / 1024, 1).' Ko',
                                'Uploadé' => $this->selectedMedia->created_at?->format('d/m/Y H:i'),
                            ] as $k => $v)
                                <div class="bg-gray-50 dark:bg-white/5 rounded-md p-3">
                                    <dt class="font-semibold uppercase tracking-wider mb-1" style="font-size:0.625rem;">{{ $k }}</dt>
                                    <dd class="text-gray-900 dark:text-gray-100 m-0 break-all">{{ $v }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <form
                            id="mlib-edit-form"
                            x-on:submit.prevent="await $wire.saveSelectedMedia(); close();"
                            class="flex flex-col gap-5"
                        >
                            <div>
                                <label class="mlib-label">Nom du fichier <span class="text-gray-400">(sans extension)</span></label>
                                <input type="text" wire:model="editName" class="mlib-input font-mono" />
                                @error('editName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                <p class="mt-1.5 text-xs text-gray-500">Renomme le fichier physique sur disque et ses conversions.</p>
                            </div>
                            <div>
                                <label class="mlib-label">Titre <span class="text-gray-400">(balise <code class="font-mono">title</code>)</span></label>
                                <input type="text" wire:model="editTitle" class="mlib-input" />
                            </div>
                            <div>
                                <label class="mlib-label">Alt <span class="text-gray-400">(accessibilité + SEO)</span></label>
                                <textarea wire:model="editAlt" rows="3" class="mlib-input"></textarea>
                            </div>
                        </form>

                        <div class="pt-4 border-t border-gray-200 dark:border-white/10">
                            <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-3 m-0">Utilisé par</h3>
                            @if (empty($this->usages))
                                <p class="text-xs text-gray-500 dark:text-gray-400 italic">Aucune utilisation. Ce média peut être supprimé sans impact.</p>
                            @else
                                <ul class="list-none p-0 m-0 flex flex-col gap-1.5">
                                    @foreach ($this->usages as $usage)
                                        <li class="flex items-start justify-between gap-3 bg-gray-50 dark:bg-white/5 px-3 py-2 rounded">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                    {{ $usage['label'] }}
                                                    <span class="normal-case tracking-normal text-gray-400">· {{ $usage['mediagroup'] }}</span>
                                                </div>
                                                <div class="text-xs text-gray-900 dark:text-gray-100 font-medium truncate">{{ $usage['title'] }}</div>
                                            </div>
                                            @if ($usage['url'])
                                                <a href="{{ $usage['url'] }}" target="_blank" class="shrink-0 text-xs text-primary-600 hover:underline font-semibold">
                                                    Ouvrir ↗
                                                </a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="pt-4 border-t border-gray-200 dark:border-white/10">
                            <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-3 m-0">Tailles générées</h3>
                            @if (empty($this->conversionsList))
                                <p class="text-xs text-gray-500 dark:text-gray-400 italic">Aucune conversion. Le cron d'auto-redimensionnement créera les variantes selon les tailles configurées.</p>
                            @else
                                <ul class="list-none p-0 m-0 flex flex-col gap-1.5">
                                    @foreach ($this->conversionsList as $conv)
                                        <li class="flex items-center justify-between text-xs font-mono bg-gray-50 dark:bg-white/5 px-3 py-2 rounded">
                                            <span class="font-bold">{{ $conv['name'] }}</span>
                                            <a href="{{ $conv['url'] }}" target="_blank" class="text-primary-600 hover:underline">voir ↗</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                    </div>

                    <footer class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 shrink-0">
                        <button
                            type="button"
                            x-on:click="if (confirm('Supprimer ce média ?')) { await $wire.deleteSelectedMedia(); close(); }"
                            class="text-sm text-red-600 hover:text-red-700 font-semibold bg-transparent border-0 cursor-pointer"
                        >
                            Supprimer
                        </button>
                        <x-filament::button
                            type="button"
                            icon="heroicon-o-check"
                            x-on:click="await $wire.saveSelectedMedia(); close();"
                        >
                            Enregistrer
                        </x-filament::button>
                    </footer>
                </aside>

                {{-- Lightbox plein écran --}}
                @if (str_starts_with((string) $this->selectedMedia->mime_type, 'image/'))
                    <div
                        x-show="lightbox"
                        x-cloak
                        x-on:keydown.escape.window="lightbox = false"
                        @click.self="lightbox = false"
                        class="mlib-lightbox"
                    >
                        <button type="button" @click="lightbox = false" class="mlib-lightbox-close" title="Fermer (Échap)">
                            <x-filament::icon icon="heroicon-o-x-mark" class="w-7 h-7" />
                        </button>
                        <img src="{{ $this->selectedMedia->getUrl() }}" alt="{{ $this->selectedMedia->getCustomProperty('alt') ?? '' }}" class="mlib-lightbox-img" @click.stop />
                    </div>
                @endif
            </div>
        </template>
    @endif

    <script>
        if (typeof window.mlibUploader === 'undefined') {
            window.mlibUploader = function () {
                return {
                    dragover: false,
                    pending: [],

                    handleFiles(fileList) {
                        const files = Array.from(fileList || []);
                        for (const file of files) {
                            this.enqueue(file);
                        }
                    },

                    enqueue(file) {
                        const id = 'p-' + Math.random().toString(36).slice(2, 10);
                        const isImage = file.type.startsWith('image/');
                        const item = {
                            id,
                            name: file.name,
                            previewUrl: isImage ? URL.createObjectURL(file) : null,
                            progress: 0,
                            error: false,
                        };
                        this.pending.push(item);

                        this.$wire.upload(
                            'pendingUpload',
                            file,
                            async () => {
                                try {
                                    await this.$wire.persistPendingUpload(file.name);
                                } catch (e) {
                                    console.error(e);
                                    item.error = true;
                                    return;
                                }
                                this.remove(item);
                            },
                            (err) => {
                                console.error('Upload error', err);
                                item.error = true;
                            },
                            (event) => {
                                item.progress = event.detail.progress;
                            }
                        );
                    },

                    remove(item) {
                        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
                        this.pending = this.pending.filter(p => p.id !== item.id);
                    },
                };
            };
        }
    </script>
</x-filament-panels::page>
