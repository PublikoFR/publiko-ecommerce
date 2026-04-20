@php
    $src = null;
    if (! empty($block['media_id']) && class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class)) {
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($block['media_id']);
        $src = $media?->getFullUrl();
    }
    $src = $src ?? ($block['url'] ?? null);
    $alt = $block['alt'] ?? '';
@endphp

@if ($src)
    <figure class="pko-pb-block pko-pb-block--image">
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            loading="lazy"
            class="w-full h-auto rounded"
        />
        @if ($alt !== '')
            <figcaption class="mt-1 text-xs text-gray-500">{{ $alt }}</figcaption>
        @endif
    </figure>
@endif
