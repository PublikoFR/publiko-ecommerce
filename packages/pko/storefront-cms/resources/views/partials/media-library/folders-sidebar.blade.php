{{--
    Sidebar dossiers — style minimaliste unifié (modal + page).

    Variables attendues :
    - $folders : Collection<Folder> (dossier par défaut déjà épinglé en tête)
    - $currentFolderId : ?int
    - $canCreateFolder : bool  (true uniquement en mode page ; donne aussi accès au "définir par défaut")
    - $defaultFolderId : ?int
--}}
<aside class="mlib-sidebar">
    <div class="mlib-sidebar-header">
        <h3 class="mlib-sidebar-title">Dossiers</h3>
        @if ($canCreateFolder ?? false)
            <button type="button" wire:click="$set('showCreateFolderModal', true)" class="mlib-sidebar-action">+ Nouveau</button>
        @endif
    </div>

    @if ($folders->isEmpty())
        <p class="mlib-sidebar-empty">
            @if ($canCreateFolder ?? false)
                Aucun dossier. Clique « + Nouveau ».
            @else
                Aucun dossier. Crée-en un depuis la médiathèque.
            @endif
        </p>
    @else
        <ul class="mlib-folder-list">
            @foreach ($folders as $folder)
                @php $isDefault = ($defaultFolderId ?? null) === $folder->id; @endphp
                <li class="mlib-folder-item {{ $isDefault ? 'is-default' : '' }}">
                    <button
                        type="button"
                        wire:click="selectFolder({{ $folder->id }})"
                        class="mlib-folder-btn {{ $currentFolderId === $folder->id ? 'is-active' : '' }}"
                    >
                        <x-filament::icon icon="heroicon-o-folder" class="w-4 h-4 shrink-0" />
                        <span class="flex-1 truncate">{{ $folder->name }}</span>
                    </button>
                    @if ($isDefault)
                        <span class="mlib-folder-indicator is-default" title="Dossier par défaut">
                            <x-filament::icon icon="heroicon-s-star" class="w-3.5 h-3.5" />
                        </span>
                    @elseif ($canCreateFolder ?? false)
                        <button
                            type="button"
                            wire:click.stop="setDefaultFolder({{ $folder->id }})"
                            class="mlib-folder-indicator is-pin"
                            title="Définir comme dossier par défaut"
                        >
                            <x-filament::icon icon="heroicon-o-star" class="w-3.5 h-3.5" />
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</aside>
