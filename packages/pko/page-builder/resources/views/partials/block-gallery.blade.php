@php
    $ids = is_array($block['media_ids'] ?? null) ? $block['media_ids'] : [];
    $cols = in_array((int) ($block['columns'] ?? 3), [2, 3, 4], true) ? (int) $block['columns'] : 3;
    $urls = [];
    if (! empty($ids) && class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class)) {
        $medias = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            if ($m = $medias->get($id)) {
                $urls[] = $m->getFullUrl();
            }
        }
    }
    $grid = match ($cols) {
        2 => 'grid-cols-2',
        4 => 'grid-cols-2 md:grid-cols-4',
        default => 'grid-cols-2 md:grid-cols-3',
    };
@endphp
@if (! empty($urls))
    <div class="pko-pb-block pko-pb-block--gallery my-3 grid gap-3 {{ $grid }}">
        @foreach ($urls as $u)
            <img src="{{ $u }}" alt="" loading="lazy" class="w-full h-auto rounded-lg object-cover" style="aspect-ratio:1/1" />
        @endforeach
    </div>
@endif
