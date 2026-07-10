@php
    $text = trim((string) ($block['text'] ?? ''));
    $variant = $block['variant'] ?? 'info';
    $cfg = match ($variant) {
        'warning' => ['wrap' => 'bg-warning-50 border-warning-500 text-warning-700', 'icon' => 'M12 3 2 20h20L12 3Zm0 6v5m0 3h.01'],
        'danger' => ['wrap' => 'bg-danger-50 border-danger-500 text-danger-700', 'icon' => 'M12 9v4m0 3h.01M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z'],
        default => ['wrap' => 'bg-info-50 border-info-500 text-info-700', 'icon' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 5h.01M11 12h1v4h1'],
    };
@endphp
@if ($text !== '')
    <div class="pko-pb-block pko-pb-block--callout flex items-start gap-3 border-l-4 rounded-r-lg py-3 pl-4 pr-4 my-2 {{ $cfg['wrap'] }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 flex-none mt-0.5">
            <path d="{{ $cfg['icon'] }}" />
        </svg>
        <p class="m-0 text-sm leading-relaxed text-neutral-800">{{ $text }}</p>
    </div>
@endif
