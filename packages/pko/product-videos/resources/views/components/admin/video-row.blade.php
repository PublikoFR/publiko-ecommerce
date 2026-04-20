@props([
    'index' => 0,
    'video' => [],
    'providers' => [],
])

@php
    $id = (int) ($video['id'] ?? 0);
    $url = (string) ($video['url'] ?? '');
    $title = (string) ($video['title'] ?? '');
    $provider = $video['provider'] ?? null; // string code or null
    $providerLabel = $provider && isset($providers[$provider]) ? $providers[$provider] : null;
    $thumb = $video['thumbnail'] ?? null;
@endphp

<div
    data-id="{{ $id > 0 ? $id : 'new-'.$index }}"
    class="flex items-start gap-3 rounded-md border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900/40"
>
    <button
        type="button"
        class="pko-video-handle mt-1 cursor-grab select-none text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
        title="Glisser pour réordonner"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
            <path d="M7 4a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm6-10a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm0 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/>
        </svg>
    </button>

    @if ($thumb)
        <img src="{{ $thumb }}" alt="" class="h-14 w-24 flex-none rounded border border-gray-200 object-cover dark:border-white/10" />
    @else
        <div class="flex h-14 w-24 flex-none items-center justify-center rounded border border-dashed border-gray-300 text-xs text-gray-400 dark:border-white/10">
            {{ $providerLabel ?? '—' }}
        </div>
    @endif

    <div class="flex flex-1 flex-col gap-2 min-w-0">
        <div class="flex items-center gap-2">
            <input
                type="url"
                wire:model.blur="videos.{{ $index }}.url"
                wire:change="detectVideoProvider({{ $index }})"
                placeholder="https://www.youtube.com/watch?v=…"
                class="flex-1 text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900"
            />
            @if ($providerLabel)
                <span class="inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                    {{ $providerLabel }}
                </span>
            @elseif ($url !== '')
                <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
                    URL non supportée
                </span>
            @endif
        </div>
        <input
            type="text"
            wire:model.blur="videos.{{ $index }}.title"
            placeholder="Titre (optionnel)"
            class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900"
        />
    </div>

    <button
        type="button"
        wire:click="removeVideoRow({{ $index }})"
        class="mt-1 text-gray-400 hover:text-danger-600"
        title="Supprimer"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
            <path fill-rule="evenodd" d="M9 2a1 1 0 0 0-.894.553L7.382 4H4a1 1 0 1 0 0 2v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V6a1 1 0 1 0 0-2h-3.382l-.724-1.447A1 1 0 0 0 11 2H9Zm-2 6a1 1 0 1 1 2 0v7a1 1 0 1 1-2 0V8Zm5-1a1 1 0 0 0-1 1v7a1 1 0 1 0 2 0V8a1 1 0 0 0-1-1Z" clip-rule="evenodd"/>
        </svg>
    </button>
</div>
