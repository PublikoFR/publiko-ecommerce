@props([
    'index' => 0,
    'document' => [],
    'categories' => [],
])

@php
    $id = (int) ($document['id'] ?? 0);
    $mediaName = (string) ($document['media_name'] ?? '');
    $categoryId = $document['category_id'] ?? null;
    $statePath = 'document-row-' . $index;
@endphp

<div
    data-id="{{ $id > 0 ? $id : 'new-' . $index }}"
    x-data="{
        init() {
            Livewire.on('media-picked', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;
                if (!data || data.statePath !== '{{ $statePath }}') return;
                const media = Array.isArray(data.medias) ? data.medias[0] : null;
                if (!media) return;
                $wire.documentPicked({{ $index }}, media.id, media.fileName ?? '');
            });
        }
    }"
    class="flex items-center gap-3 rounded-md border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900/40"
>
    <button
        type="button"
        class="pko-doc-handle mt-0.5 cursor-grab select-none text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
        title="Glisser pour réordonner"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
            <path d="M7 4a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm6-10a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/>
        </svg>
    </button>

    {{-- Icône document --}}
    <div class="flex h-10 w-10 flex-none items-center justify-center rounded border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-gray-400">
            <path fill-rule="evenodd" d="M4 4a2 2 0 0 1 2-2h4.586A2 2 0 0 1 12 2.586L15.414 6A2 2 0 0 1 16 7.414V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm2 6a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H7a1 1 0 0 1-1-1Zm1 3a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H7Z" clip-rule="evenodd"/>
        </svg>
    </div>

    <div class="flex flex-1 flex-col gap-2 min-w-0">
        {{-- Nom du media + bouton choisir --}}
        <div class="flex items-center gap-2">
            <span class="flex-1 truncate text-sm text-gray-700 dark:text-gray-200">
                @if ($mediaName !== '')
                    {{ $mediaName }}
                @else
                    <span class="text-gray-400 italic">Aucun fichier sélectionné</span>
                @endif
            </span>
            <button
                type="button"
                @click="Livewire.dispatch('open-media-picker-modal', { statePath: '{{ $statePath }}', multiple: false })"
                class="shrink-0 rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-600 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300"
            >
                Choisir
            </button>
        </div>

        {{-- Catégorie --}}
        <select
            wire:model.live="documents.{{ $index }}.category_id"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200"
        >
            <option value="">— Sans catégorie —</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}" @selected((int) $categoryId === (int) $cat->id)>{{ $cat->label }}</option>
            @endforeach
        </select>
    </div>

    <button
        type="button"
        wire:click="removeDocumentRow({{ $index }})"
        class="mt-0.5 shrink-0 text-gray-400 hover:text-danger-600"
        title="Supprimer"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
            <path fill-rule="evenodd" d="M9 2a1 1 0 0 0-.894.553L7.382 4H4a1 1 0 1 0 0 2v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V6a1 1 0 1 0 0-2h-3.382l-.724-1.447A1 1 0 0 0 11 2H9Zm-2 6a1 1 0 1 1 2 0v7a1 1 0 1 1-2 0V8Zm5-1a1 1 0 0 0-1 1v7a1 1 0 1 0 2 0V8a1 1 0 0 0-1-1Z" clip-rule="evenodd"/>
        </svg>
    </button>
</div>
