{{-- Responsive 16:9 video embed. Uses CSS aspect-ratio (wide browser support,
     no padding-top hack needed). For MP4, a native HTML5 player is used. --}}
<div
    {{ $attributes->merge(['class' => 'pko-product-video relative w-full overflow-hidden']) }}
    style="aspect-ratio: 16 / 9;"
>
    @if ($isIframe())
        <iframe
            class="absolute inset-0 h-full w-full"
            src="{{ $info->embedUrl }}"
            title="{{ $title ?? $info->provider->label() }}"
            loading="lazy"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
            referrerpolicy="strict-origin-when-cross-origin"
        ></iframe>
    @else
        <video
            class="absolute inset-0 h-full w-full bg-black"
            src="{{ $info->embedUrl }}"
            controls
            preload="metadata"
            playsinline
        >
            Votre navigateur ne supporte pas la lecture vidéo HTML5.
        </video>
    @endif
</div>
