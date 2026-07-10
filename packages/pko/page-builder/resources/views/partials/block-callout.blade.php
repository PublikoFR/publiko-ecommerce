@php
    $text = trim((string) ($block['text'] ?? ''));
    $variant = $block['variant'] ?? 'info';
    // Couleurs pilotées en inline (indépendant du scan Tailwind) : fond très
    // clair (teinte -100), bordure gauche -500, texte/icône -700.
    $cfg = match ($variant) {
        'warning' => ['bg' => '#fbeccb', 'bd' => '#e8a317', 'tx' => '#a36f08', 'icon' => 'M12 3 2 20h20L12 3Zm0 6v5m0 3h.01'],
        'danger' => ['bg' => '#f9dcdc', 'bd' => '#d64545', 'tx' => '#9c2a2a', 'icon' => 'M12 9v4m0 3h.01M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z'],
        default => ['bg' => '#d6e9f3', 'bd' => '#2f80b8', 'tx' => '#1d5781', 'icon' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 5h.01M11 12h1v4h1'],
    };
@endphp
@if ($text !== '')
    <div class="pko-pb-block pko-pb-block--callout flex items-start gap-3 border-l-4 rounded-r-lg py-3 pl-4 pr-4 my-2"
         style="background-color:{{ $cfg['bg'] }};border-left-color:{{ $cfg['bd'] }};color:{{ $cfg['tx'] }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 flex-none mt-0.5">
            <path d="{{ $cfg['icon'] }}" />
        </svg>
        <p class="m-0 text-sm leading-relaxed" style="color:#1f2937">{{ $text }}</p>
    </div>
@endif
