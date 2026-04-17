<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
        {{-- Sidebar Folders --}}
        <aside class="bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold text-sm uppercase tracking-wider text-gray-700 dark:text-gray-300">Dossiers</h3>
                <a href="{{ route('filament.admin.resources.folders.create') }}" class="text-xs text-primary-600 hover:text-primary-700 font-semibold">+ Nouveau</a>
            </div>

            <button type="button" wire:click="selectFolder(null)" class="w-full text-left px-3 py-2 text-sm rounded-md mb-1 {{ $currentFolderId === null ? 'bg-primary-50 text-primary-700 font-semibold dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/5' }}">
                📂 Racine (sélectionnez un dossier)
            </button>

            <ul class="space-y-0.5">
                @foreach ($this->foldersTree as $folder)
                    <li>
                        <button type="button" wire:click="selectFolder({{ $folder['id'] }})" class="w-full text-left px-3 py-1.5 text-sm rounded-md flex items-center gap-2 {{ $currentFolderId === $folder['id'] ? 'bg-primary-50 text-primary-700 font-semibold dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/5' }}">
                            <span>📁</span>
                            <span class="truncate flex-1">{{ $folder['name'] }}</span>
                            <span class="text-xs text-gray-400">{{ $folder['collection'] }}</span>
                        </button>
                        @if (! empty($folder['children']))
                            <ul class="pl-4 border-l border-gray-200 dark:border-white/10 ml-3 space-y-0.5">
                                @foreach ($folder['children'] as $child)
                                    <li>
                                        <button type="button" wire:click="selectFolder({{ $child['id'] }})" class="w-full text-left px-3 py-1.5 text-xs rounded-md flex items-center gap-2 {{ $currentFolderId === $child['id'] ? 'bg-primary-50 text-primary-700 font-semibold dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/5' }}">
                                            <span>📁</span>
                                            <span class="truncate">{{ $child['name'] }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </aside>

        {{-- Main content --}}
        <div class="space-y-6">
            @if ($currentFolderId === null)
                <div class="bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 p-12 text-center">
                    <x-filament::icon icon="heroicon-o-folder-open" class="w-16 h-16 mx-auto text-gray-300" />
                    <p class="mt-4 font-semibold text-gray-700 dark:text-gray-300">Sélectionnez un dossier</p>
                    <p class="text-sm text-gray-500 mt-1">Cliquez un dossier dans la barre latérale pour voir ses médias et en uploader.</p>
                </div>
            @else
                {{-- Dropzone --}}
                <div class="bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-bold text-gray-900 dark:text-white">📁 {{ $this->currentFolder?->name }}</h2>
                        <span class="text-xs text-gray-500">Collection <code class="bg-gray-100 dark:bg-white/10 px-1.5 py-0.5 rounded">{{ $this->currentFolder?->collection }}</code></span>
                    </div>

                    <form wire:submit="saveUploads" class="space-y-3">
                        {{ $this->form }}
                        <div class="flex justify-end">
                            <x-filament::button
                                type="submit"
                                icon="heroicon-o-cloud-arrow-up"
                                wire:loading.attr="disabled"
                                wire:target="saveUploads, uploadState.files"
                            >
                                <span wire:loading.remove wire:target="saveUploads">Uploader dans le dossier</span>
                                <span wire:loading wire:target="saveUploads">Upload…</span>
                            </x-filament::button>
                        </div>
                    </form>
                </div>

                {{-- Media grid --}}
                <div class="bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 p-5">
                    <h3 class="font-bold mb-4 text-gray-900 dark:text-white">Médias <span class="text-sm font-normal text-gray-500">({{ $this->medias->count() }})</span></h3>

                    @if ($this->medias->isEmpty())
                        <p class="text-center text-gray-500 py-12 text-sm">Aucun média dans ce dossier. Uploadez vos premiers fichiers via la zone ci-dessus.</p>
                    @else
                        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
                            @foreach ($this->medias as $media)
                                <button type="button" wire:click="openMedia({{ $media->id }})" class="group relative aspect-square bg-gray-50 dark:bg-white/5 rounded-lg overflow-hidden ring-1 ring-gray-200 dark:ring-white/10 hover:ring-2 hover:ring-primary-500 transition">
                                    @if (str_starts_with((string) $media->mime_type, 'image/'))
                                        <img src="{{ $media->getUrl() }}" alt="{{ $media->getCustomProperty('alt') ?? '' }}" loading="lazy" class="w-full h-full object-cover" />
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <x-filament::icon icon="heroicon-o-document" class="w-8 h-8" />
                                        </div>
                                    @endif
                                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent text-white text-[10px] px-2 py-1 truncate text-left">
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

    {{-- Slide-over Edit --}}
    @if ($this->selectedMedia)
        <div class="fixed inset-0 z-50 overflow-hidden">
            <div class="absolute inset-0 bg-black/40" wire:click="closeMedia"></div>
            <aside class="absolute top-0 right-0 h-full w-full max-w-lg bg-white dark:bg-gray-900 shadow-2xl flex flex-col overflow-y-auto">
                <header class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-white/10">
                    <h2 class="font-bold text-gray-900 dark:text-white">Édition média</h2>
                    <button type="button" wire:click="closeMedia" class="text-gray-400 hover:text-gray-600">
                        <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                    </button>
                </header>

                <div class="p-5 space-y-5">
                    @if (str_starts_with((string) $this->selectedMedia->mime_type, 'image/'))
                        <div class="bg-gray-50 dark:bg-white/5 rounded-lg overflow-hidden aspect-video flex items-center justify-center p-4">
                            <img src="{{ $this->selectedMedia->getUrl() }}" alt="" class="max-w-full max-h-full object-contain" />
                        </div>
                    @endif

                    <dl class="text-xs grid grid-cols-2 gap-2 text-gray-600 dark:text-gray-400">
                        <div><dt class="font-semibold">Fichier actuel</dt><dd class="font-mono break-all">{{ $this->selectedMedia->file_name }}</dd></div>
                        <div><dt class="font-semibold">Type</dt><dd>{{ $this->selectedMedia->mime_type }}</dd></div>
                        <div><dt class="font-semibold">Taille</dt><dd>{{ number_format($this->selectedMedia->size / 1024, 1) }} Ko</dd></div>
                        <div><dt class="font-semibold">Uploadé</dt><dd>{{ $this->selectedMedia->created_at?->format('d/m/Y H:i') }}</dd></div>
                    </dl>

                    <form wire:submit="saveSelectedMedia" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom du fichier (sans extension)</label>
                            <input type="text" wire:model="editName" class="block w-full rounded-md border-gray-300 dark:bg-white/5 dark:border-white/10 text-sm font-mono" />
                            @error('editName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            <p class="mt-1 text-xs text-gray-500">Renomme le fichier physique sur disque et ses conversions associées.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Titre (balise title)</label>
                            <input type="text" wire:model="editTitle" class="block w-full rounded-md border-gray-300 dark:bg-white/5 dark:border-white/10 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alt (accessibilité + SEO)</label>
                            <textarea wire:model="editAlt" rows="2" class="block w-full rounded-md border-gray-300 dark:bg-white/5 dark:border-white/10 text-sm"></textarea>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-gray-200 dark:border-white/10">
                            <button type="button" wire:click="deleteSelectedMedia" wire:confirm="Supprimer ce média ?" class="text-sm text-red-600 hover:text-red-700 font-semibold">
                                Supprimer
                            </button>
                            <x-filament::button type="submit" icon="heroicon-o-check">Enregistrer</x-filament::button>
                        </div>
                    </form>

                    <div class="pt-4 border-t border-gray-200 dark:border-white/10">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-3">Tailles générées</h3>
                        @if (empty($this->conversionsList))
                            <p class="text-xs text-gray-500 italic">Aucune conversion. Le système d'auto-redimensionnement (cron) créera les variantes selon les tailles configurées.</p>
                        @else
                            <ul class="space-y-1">
                                @foreach ($this->conversionsList as $conv)
                                    <li class="flex items-center justify-between text-xs font-mono bg-gray-50 dark:bg-white/5 px-3 py-2 rounded">
                                        <span class="font-bold">{{ $conv['name'] }}</span>
                                        <a href="{{ $conv['url'] }}" target="_blank" class="text-primary-600 hover:underline">voir ↗</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <a href="{{ $this->selectedMedia->getUrl() }}" target="_blank" class="block text-center text-xs text-primary-600 hover:underline">Ouvrir le fichier original dans un onglet ↗</a>
                </div>
            </aside>
        </div>
    @endif
</x-filament-panels::page>
