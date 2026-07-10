@php
    $text = trim((string) ($block['text'] ?? ''));
    $level = in_array($block['level'] ?? null, ['h2', 'h3'], true) ? $block['level'] : 'h2';
    $cls = $level === 'h3'
        ? 'text-xl md:text-2xl font-display font-semibold text-neutral-900 mt-4 mb-2'
        : 'text-2xl md:text-3xl font-display font-bold text-neutral-900 mt-6 mb-3';
@endphp
@if ($text !== '')
    <{{ $level }} class="pko-pb-block pko-pb-block--title {{ $cls }}">{{ $text }}</{{ $level }}>
@endif
