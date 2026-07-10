@php
    $url = trim((string) ($block['url'] ?? ''));
    $info = null;
    if ($url !== '' && class_exists(\Pko\ProductVideos\Services\VideoUrlResolver::class)) {
        $info = app(\Pko\ProductVideos\Services\VideoUrlResolver::class)->tryResolve($url);
    }
@endphp
@if ($info)
    <div class="pko-pb-block pko-pb-block--video my-3">
        <div class="relative w-full overflow-hidden rounded-lg bg-neutral-900" style="aspect-ratio:16/9">
            @if ($info->provider->isIframe())
                <iframe src="{{ $info->embedUrl }}" title="Vidéo" loading="lazy"
                    class="absolute inset-0 h-full w-full" style="border:0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
            @else
                <video src="{{ $info->embedUrl }}" controls preload="metadata" class="absolute inset-0 h-full w-full"></video>
            @endif
        </div>
    </div>
@endif
