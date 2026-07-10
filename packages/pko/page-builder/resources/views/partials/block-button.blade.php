@php
    $label = trim((string) ($block['label'] ?? ''));
    $url = trim((string) ($block['url'] ?? ''));
    $variant = $block['variant'] ?? 'primary';
    $classes = match ($variant) {
        'accent' => 'bg-accent-500 text-primary-900 hover:bg-accent-600',
        'secondary' => 'bg-white text-primary-700 border border-neutral-300 hover:border-primary-400',
        default => 'bg-primary-600 text-white hover:bg-primary-700',
    };
@endphp
@if ($label !== '')
    <div class="pko-pb-block pko-pb-block--button my-2">
        <a href="{{ $url !== '' ? $url : '#' }}"
           class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold transition {{ $classes }}"
           @if (\Illuminate\Support\Str::startsWith($url, 'http')) rel="noopener" @endif>
            {{ $label }}
        </a>
    </div>
@endif
