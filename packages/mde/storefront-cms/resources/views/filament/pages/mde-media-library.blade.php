<x-filament-panels::page>
    <div x-data class="media-library-layout" style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
        <style>
            @media (min-width: 1024px) { .media-library-layout { grid-template-columns: 280px minmax(0, 1fr) !important; } }
        </style>
        {{-- Sidebar Folders --}}
        <aside class="bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 p-5 h-fit lg:sticky lg:top-24">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-sm uppercase tracking-wider text-gray-700 dark:text-gray-300">Dossiers</h3>
                <button type="button" wire:click="$set('showCreateFolderModal', true)" class="text-xs text-primary-600 hover:text-primary-700 font-semibold">+ Nouveau</button>
            </div>

            <ul class="space-y-1">
                @foreach ($this->foldersTree as $folder)
                    <li class="group">
                        <div class="flex items-center gap-1">
                            <button type="button" wire:click="selectFolder({{ $folder['id'] }})" class="flex-1 text-left px-3 py-2 text-sm rounded-md flex items-center gap-2 transition {{ $currentFolderId === $folder['id'] ? 'bg-primary-50 text-primary-700 font-semibold dark:bg-primary-500/10 dark:text-primary-300' : 'hover:bg-gray-50 dark:hover:bg-white/5 text-gray-700 dark:text-gray-300' }}">
                                <x-filament::icon icon="heroicon-o-folder" class="w-4 h-4 shrink-0" />
                                <span class="truncate flex-1">{{ $folder['name'] }}</span>
                                <span class="text-[10px] text-gray-400 font-mono">{{ $folder['collection'] }}</span>
                            </button>
                            <button type="button" wire:click="deleteFolder({{ $folder['id'] }})" wire:confirm="Supprimer le dossier « {{ $folder['name'] }} » et ses médias ?" class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-red-600 p-1 transition" title="Supprimer">
                                <x-filament::icon icon="heroicon-o-trash" class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if (empty($this->foldersTree))
                <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-6">Aucun dossier.<br>Cliquez « + Nouveau ».</p>
            @endif
        </aside>

        {{-- Main content --}}
        <div class="space-y-6" style="min-width: 0;">
            @if ($currentFolderId === null)
                <div class="bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 p-16 text-center">
                    <x-filament::icon icon="heroicon-o-folder-open" class="w-16 h-16 mx-auto text-gray-300" />
                    <p class="mt-4 font-semibold text-gray-700 dark:text-gray-300">Sélectionnez un dossier</p>
                    <p class="text-sm text-gray-500 mt-1">Cliquez sur un dossier dans la barre latérale pour voir ses médias.</p>
                </div>
            @else
                {{-- Dropzone --}}
                <div class="bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-s-folder" class="w-5 h-5 text-primary-600" />
                            <h2 class="font-bold text-lg text-gray-900 dark:text-white">{{ $this->currentFolder?->name }}</h2>
                        </div>
                        <span class="text-xs text-gray-500">Collection <code class="bg-gray-100 dark:bg-white/10 px-1.5 py-0.5 rounded font-mono">{{ $this->currentFolder?->collection }}</code></span>
                    </div>

                    <div wire:loading.class="opacity-60 pointer-events-none" wire:target="saveUploads, uploadState.files">
                        {{ $this->form }}
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-3 text-center" wire:loading wire:target="saveUploads">
                        <x-filament::loading-indicator class="w-4 h-4 inline-block" />
                        Upload en cours…
                    </p>
                </div>

                {{-- Media grid --}}
                <div class="bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 p-6">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-5">Médias <span class="text-sm font-normal text-gray-500">({{ $this->medias->count() }})</span></h3>

                    @if ($this->medias->isEmpty())
                        <p class="text-center text-gray-500 py-16 text-sm">Aucun média dans ce dossier. Uploadez des fichiers via la zone ci-dessus.</p>
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                            @foreach ($this->medias as $media)
                                <button type="button" wire:click="openMedia({{ $media->id }})" class="group relative aspect-square bg-gray-100 dark:bg-white/5 rounded-lg overflow-hidden ring-1 ring-gray-200 dark:ring-white/10 hover:ring-2 hover:ring-primary-500 transition focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    @if (str_starts_with((string) $media->mime_type, 'image/'))
                                        <img src="{{ $media->getUrl() }}" alt="{{ $media->getCustomProperty('alt') ?? '' }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover" />
                                    @else
                                        <div class="absolute inset-0 flex items-center justify-center text-gray-300">
                                            <x-filament::icon icon="heroicon-o-document" class="w-10 h-10" />
                                        </div>
                                    @endif
                                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent text-white text-[10px] px-2 py-1.5 truncate text-left font-medium">
                                        {{ $media->file_name }}
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Create Folder --}}
    @if ($showCreateFolderModal)
        <div
            x-data
            x-init="document.body.style.overflow='hidden'"
            x-on:keydown.escape.window="$wire.set('showCreateFolderModal', false)"
            x-on:remove.once="document.body.style.overflow=''"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <div x-transition.opacity class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm" wire:click="$set('showCreateFolderModal', false)"></div>
            <div x-transition class="relative bg-white dark:bg-gray-900 rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
                <header class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-white/10">
                    <h2 class="font-bold text-lg text-gray-900 dark:text-white">Nouveau dossier</h2>
                    <button type="button" wire:click="$set('showCreateFolderModal', false)" class="text-gray-400 hover:text-gray-600">
                        <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                    </button>
                </header>
                <form wire:submit="createFolder" class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nom du dossier</label>
                        <input type="text" wire:model="newFolderName" required class="block w-full rounded-md border-gray-300 dark:bg-white/5 dark:border-white/10 text-sm" placeholder="Ex: Produits Milwaukee" autofocus />
                        @error('newFolderName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Collection Spatie</label>
                        <input type="text" wire:model="newFolderCollection" required class="block w-full rounded-md border-gray-300 dark:bg-white/5 dark:border-white/10 text-sm font-mono" placeholder="default" />
                        <p class="mt-1.5 text-xs text-gray-500">Identifiant technique (slug). Utilise <code class="bg-gray-100 dark:bg-white/10 px-1 rounded">default</code> sauf si tu sais ce que tu fais. Les médias Lunar sont sous <code class="bg-gray-100 dark:bg-white/10 px-1 rounded">products</code>.</p>
                        @error('newFolderCollection')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-2 pt-4 border-t border-gray-200 dark:border-white/10 -mx-6 -mb-6 px-6 py-4 bg-gray-50 dark:bg-white/5">
                        <x-filament::button type="button" color="gray" wire:click="$set('showCreateFolderModal', false)">Annuler</x-filament::button>
                        <x-filament::button type="submit">Créer</x-filament::button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Slide-over Edit --}}
    <div
        x-data="{ open: @entangle('selectedMediaId') }"
        x-effect="document.body.style.overflow = (open !== null && open !== '' && open !== 0) ? 'hidden' : ''"
        x-cloak
    >
        @if ($this->selectedMedia)
            <div class="fixed inset-0 z-50 overflow-hidden">
                <div
                    x-transition:enter="transition-opacity ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"
                    wire:click="closeMedia"
                ></div>
                <aside
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="absolute top-0 right-0 h-full w-full max-w-md bg-white dark:bg-gray-900 shadow-2xl flex flex-col"
                >
                    <header class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-white/10 shrink-0">
                        <h2 class="font-bold text-gray-900 dark:text-white">Édition média</h2>
                        <button type="button" wire:click="closeMedia" class="text-gray-400 hover:text-gray-600 transition">
                            <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                        </button>
                    </header>

                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        @if (str_starts_with((string) $this->selectedMedia->mime_type, 'image/'))
                            <div class="bg-gray-100 dark:bg-white/5 rounded-lg overflow-hidden aspect-video flex items-center justify-center p-3">
                                <img src="{{ $this->selectedMedia->getUrl() }}" alt="" class="max-w-full max-h-full object-contain" />
                            </div>
                        @endif

                        <dl class="grid grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-400">
                            <div class="bg-gray-50 dark:bg-white/5 rounded-md p-3">
                                <dt class="font-semibold uppercase text-[10px] tracking-wider mb-1">Fichier</dt>
                                <dd class="font-mono break-all text-gray-900 dark:text-gray-100">{{ $this->selectedMedia->file_name }}</dd>
                            </div>
                            <div class="bg-gray-50 dark:bg-white/5 rounded-md p-3">
                                <dt class="font-semibold uppercase text-[10px] tracking-wider mb-1">Type</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $this->selectedMedia->mime_type }}</dd>
                            </div>
                            <div class="bg-gray-50 dark:bg-white/5 rounded-md p-3">
                                <dt class="font-semibold uppercase text-[10px] tracking-wider mb-1">Taille</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ number_format($this->selectedMedia->size / 1024, 1) }} Ko</dd>
                            </div>
                            <div class="bg-gray-50 dark:bg-white/5 rounded-md p-3">
                                <dt class="font-semibold uppercase text-[10px] tracking-wider mb-1">Uploadé</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $this->selectedMedia->created_at?->format('d/m/Y H:i') }}</dd>
                            </div>
                        </dl>

                        <form wire:submit="saveSelectedMedia" class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nom du fichier <span class="text-gray-400">(sans extension)</span></label>
                                <input type="text" wire:model="editName" class="block w-full rounded-md border-gray-300 dark:bg-white/5 dark:border-white/10 text-sm font-mono" />
                                @error('editName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                <p class="mt-1.5 text-xs text-gray-500">Renomme le fichier physique sur disque et ses conversions.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Titre <span class="text-gray-400">(balise <code class="font-mono">title</code>)</span></label>
                                <input type="text" wire:model="editTitle" class="block w-full rounded-md border-gray-300 dark:bg-white/5 dark:border-white/10 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Alt <span class="text-gray-400">(accessibilité + SEO)</span></label>
                                <textarea wire:model="editAlt" rows="3" class="block w-full rounded-md border-gray-300 dark:bg-white/5 dark:border-white/10 text-sm"></textarea>
                            </div>
                        </form>

                        <div class="pt-4 border-t border-gray-200 dark:border-white/10">
                            <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-3">Tailles générées</h3>
                            @if (empty($this->conversionsList))
                                <p class="text-xs text-gray-500 dark:text-gray-400 italic">Aucune conversion. Le cron d'auto-redimensionnement créera les variantes selon les tailles configurées.</p>
                            @else
                                <ul class="space-y-1.5">
                                    @foreach ($this->conversionsList as $conv)
                                        <li class="flex items-center justify-between text-xs font-mono bg-gray-50 dark:bg-white/5 px-3 py-2 rounded">
                                            <span class="font-bold">{{ $conv['name'] }}</span>
                                            <a href="{{ $conv['url'] }}" target="_blank" class="text-primary-600 hover:underline">voir ↗</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <a href="{{ $this->selectedMedia->getUrl() }}" target="_blank" class="block text-center text-xs text-primary-600 hover:underline py-2">Ouvrir le fichier original ↗</a>
                    </div>

                    <footer class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 shrink-0">
                        <button type="button" wire:click="deleteSelectedMedia" wire:confirm="Supprimer ce média ?" class="text-sm text-red-600 hover:text-red-700 font-semibold">
                            Supprimer
                        </button>
                        <x-filament::button wire:click="saveSelectedMedia" icon="heroicon-o-check">Enregistrer</x-filament::button>
                    </footer>
                </aside>
            </div>
        @endif
    </div>
</x-filament-panels::page>
