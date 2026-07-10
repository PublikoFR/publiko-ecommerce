@php
    $text = trim((string) ($block['text'] ?? ''));
@endphp
@if ($text !== '')
    <figure class="pko-pb-block pko-pb-block--quote border-l-4 border-lime-500 bg-neutral-50 rounded-r-lg py-4 pl-5 pr-4 my-2">
        <blockquote class="text-lg italic leading-relaxed text-neutral-800">{{ $text }}</blockquote>
        @if (trim((string) ($block['cite'] ?? '')) !== '')
            <figcaption class="mt-2 text-sm font-medium text-neutral-500">— {{ $block['cite'] }}</figcaption>
        @endif
    </figure>
@endif
