@php $files = $getRecord()->files; @endphp
@if ($files->isEmpty())
    <p class="text-sm text-gray-500">Aucune pièce jointe.</p>
@else
    <ul class="divide-y divide-gray-100 dark:divide-white/5 text-sm">
        @foreach ($files as $file)
            <li class="flex items-center gap-3 py-2">
                <x-filament::icon :icon="$file->isImage() ? 'heroicon-o-photo' : 'heroicon-o-document'" class="h-5 w-5 text-gray-400" />
                <span class="flex-1 truncate">{{ $file->original_name }}</span>
                <span class="text-xs text-gray-500">{{ $file->humanSize() }}</span>
                <x-filament::link :href="$file->downloadUrl()" icon="heroicon-o-arrow-down-tray">
                    Télécharger
                </x-filament::link>
            </li>
        @endforeach
    </ul>
@endif
