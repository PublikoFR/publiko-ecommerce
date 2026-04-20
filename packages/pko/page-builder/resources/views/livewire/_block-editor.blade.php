@php
    $type = $block['type'] ?? null;
@endphp

<div class="flex items-start gap-2">
    <div class="flex-1 min-w-0">
        @if ($type === 'text')
            <div class="text-xs text-gray-500 uppercase tracking-wide">Texte</div>
            <div class="prose prose-sm mt-1 max-h-24 overflow-hidden rounded bg-white p-2 dark:bg-gray-900">
                @if (trim(strip_tags($block['html'] ?? '')) === '')
                    <em class="text-gray-400">Vide — cliquez sur Modifier</em>
                @else
                    {!! \Illuminate\Support\Str::limit(strip_tags($block['html']), 180) !!}
                @endif
            </div>
            <div class="mt-1">
                <button
                    type="button"
                    wire:click="mountAction('editText', { blockId: '{{ $block['id'] }}' })"
                    class="inline-flex items-center gap-1 rounded border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300"
                >
                    Modifier
                </button>
            </div>
        @elseif ($type === 'image')
            <div class="text-xs text-gray-500 uppercase tracking-wide">Image</div>
            <div class="mt-1 flex items-start gap-2">
                @php
                    $imgSrc = null;
                    if (! empty($block['media_id'])) {
                        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($block['media_id']);
                        $imgSrc = $media?->getFullUrl();
                    }
                    $imgSrc = $imgSrc ?? ($block['url'] ?? null);
                @endphp

                @if ($imgSrc)
                    <img src="{{ $imgSrc }}" class="h-16 w-24 flex-none rounded border border-gray-200 object-cover dark:border-white/10" alt="" />
                @else
                    <div class="flex h-16 w-24 flex-none items-center justify-center rounded border border-dashed border-gray-300 text-xs text-gray-400 dark:border-white/10">—</div>
                @endif

                <div class="flex-1 space-y-1">
                    <input type="text"
                        value="{{ $block['alt'] ?? '' }}"
                        wire:change="updateImageAlt('{{ $block['id'] }}', $event.target.value)"
                        placeholder="Texte alternatif (alt)"
                        class="w-full text-xs border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                    />
                    <button type="button"
                        wire:click="openImagePicker('{{ $block['id'] }}')"
                        class="inline-flex items-center gap-1 rounded border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300"
                    >
                        Choisir une image
                    </button>
                </div>
            </div>
        @elseif ($type === 'code')
            <div class="text-xs text-gray-500 uppercase tracking-wide">Code</div>
            <div class="mt-1 space-y-1">
                <select
                    wire:change="updateCodeBlock('{{ $block['id'] }}', 'language', $event.target.value)"
                    class="w-40 text-xs border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                >
                    @foreach (['plain' => 'Texte brut', 'php' => 'PHP', 'js' => 'JavaScript', 'ts' => 'TypeScript', 'html' => 'HTML', 'css' => 'CSS', 'bash' => 'Bash', 'json' => 'JSON', 'sql' => 'SQL', 'yaml' => 'YAML'] as $lang => $label)
                        <option value="{{ $lang }}" @selected($block['language'] === $lang)>{{ $label }}</option>
                    @endforeach
                </select>
                <textarea
                    wire:change="updateCodeBlock('{{ $block['id'] }}', 'content', $event.target.value)"
                    rows="4"
                    placeholder="// code"
                    class="w-full font-mono text-xs border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                >{{ $block['content'] ?? '' }}</textarea>
            </div>
        @endif
    </div>

    <button
        type="button"
        wire:click="removeBlock('{{ $block['id'] }}')"
        wire:confirm="Supprimer ce bloc ?"
        class="text-gray-400 hover:text-danger-600"
        title="Supprimer le bloc"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M9 2a1 1 0 0 0-.894.553L7.382 4H4a1 1 0 1 0 0 2v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V6a1 1 0 1 0 0-2h-3.382l-.724-1.447A1 1 0 0 0 11 2H9Zm-2 6a1 1 0 1 1 2 0v7a1 1 0 1 1-2 0V8Zm5-1a1 1 0 0 0-1 1v7a1 1 0 1 0 2 0V8a1 1 0 0 0-1-1Z" clip-rule="evenodd"/></svg>
    </button>
</div>
