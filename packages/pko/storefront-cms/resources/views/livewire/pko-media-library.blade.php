@php
    $canCreateFolder = $asPage;
    $canDeleteFolder = $asPage;
    $canBulkManage = $asPage;
    $showUsagesAndConversions = $asPage;
@endphp

<div
    x-data="{}"
    x-on:media-picker-opened.window="document.body.style.overflow = 'hidden'"
    x-on:media-picker-closed.window="document.body.style.overflow = ''"
    x-on:media-picked.window="document.body.style.overflow = ''"
>
    @if ($asPage)
        {{-- ============ PAGE MODE ============ --}}
        <div class="mlib-shell" x-data="pkoMediaLibraryUploader()">
            <div class="mlib-layout">
                @include('storefront-cms::partials.media-library.folders-sidebar', [
                    'folders' => $this->folders,
                    'currentFolderId' => $currentFolderId,
                    'canCreateFolder' => $canCreateFolder,
                    'defaultFolderId' => $this->defaultFolderId,
                ])

                @include('storefront-cms::partials.media-library.grid', [
                    'currentFolder' => $this->currentFolder,
                    'medias' => $this->medias,
                    'selectedMediaIds' => $selectedMediaIds,
                    'canDeleteFolder' => $canDeleteFolder,
                    'canBulkManage' => $canBulkManage,
                    'tileBodyAction' => 'openMedia',
                ])

                @include('storefront-cms::partials.media-library.details-drawer', [
                    'focused' => $this->selectedMedia,
                    'editName' => $editName,
                    'editTitle' => $editTitle,
                    'editAlt' => $editAlt,
                    'showUsagesAndConversions' => $showUsagesAndConversions,
                    'usages' => $this->usages,
                    'conversions' => $this->conversionsList,
                ])
            </div>
        </div>
    @elseif ($open && $this->isPicker())
        {{-- ============ PICKER MODAL ============ --}}
        <div
            x-data="pkoMediaLibraryUploader()"
            x-on:keydown.escape.window="$wire.closeModal()"
            class="mlib-picker-backdrop"
        >
            <div wire:click="closeModal" class="absolute inset-0"></div>

            <div class="mlib-picker-box">
                <header class="mlib-picker-header">
                    <h2 class="mlib-picker-title">
                        @if ($this->isMultiple())
                            Sélectionner des médias
                        @else
                            Sélectionner un média
                        @endif
                        <span class="mlib-picker-badge">{{ $mediagroup }}</span>
                    </h2>
                    <button type="button" wire:click="closeModal" class="mlib-picker-close" title="Fermer">
                        <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                    </button>
                </header>

                <div class="mlib-picker-body">
                    <div class="mlib-layout">
                        @include('storefront-cms::partials.media-library.folders-sidebar', [
                            'folders' => $this->folders,
                            'currentFolderId' => $currentFolderId,
                            'canCreateFolder' => false,
                            'defaultFolderId' => $this->defaultFolderId,
                        ])

                        @include('storefront-cms::partials.media-library.grid', [
                            'currentFolder' => $this->currentFolder,
                            'medias' => $this->medias,
                            'selectedMediaIds' => $selectedMediaIds,
                            'canDeleteFolder' => false,
                            'canBulkManage' => false,
                            'tileBodyAction' => 'toggleMediaSelection',
                        ])

                        @include('storefront-cms::partials.media-library.details-drawer', [
                            'focused' => $this->selectedMedia,
                            'editName' => $editName,
                            'editTitle' => $editTitle,
                            'editAlt' => $editAlt,
                            'showUsagesAndConversions' => false,
                            'usages' => null,
                            'conversions' => null,
                        ])
                    </div>
                </div>

                <footer class="mlib-picker-footer">
                    <span class="text-sm text-gray-500">
                        @if ($this->isMultiple())
                            {{ count($selectedMediaIds) }} sélectionné{{ count($selectedMediaIds) > 1 ? 's' : '' }}
                        @else
                            {{ ! empty($selectedMediaIds) ? '1 sélectionné' : 'Aucune sélection' }}
                        @endif
                    </span>
                    <div class="flex items-center gap-2">
                        <x-filament::button type="button" color="gray" wire:click="closeModal">Annuler</x-filament::button>
                        <x-filament::button
                            type="button"
                            wire:click="confirm"
                            :disabled="empty($selectedMediaIds)"
                        >
                            Confirmer
                        </x-filament::button>
                    </div>
                </footer>
            </div>
        </div>
    @endif

    {{-- Modal "Nouveau dossier" (page uniquement, conditionné par $showCreateFolderModal) --}}
    @if ($asPage && $showCreateFolderModal)
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
                            <p class="mt-1.5 text-xs text-gray-500">Identifiant technique (slug). Utilise <code class="bg-gray-100 dark:bg-white/10 px-1 rounded">default</code> sauf si tu sais ce que tu fais.</p>
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

    {{-- Modal "Déplacer vers" (page uniquement) --}}
    @if ($asPage && $showBulkMoveModal)
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

    @script
    <script>
        window.pkoMediaLibraryUploader = function () {
            return {
                dragover: false,
                urlImport: false,
                pending: [],
                handleFiles(fileList) {
                    const files = Array.from(fileList || []);
                    for (const file of files) this.enqueue(file);
                },
                enqueue(file) {
                    const id = 'pml-' + Math.random().toString(36).slice(2, 10);
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
