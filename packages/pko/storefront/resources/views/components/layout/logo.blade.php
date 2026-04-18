@props([])
@php
$name = brand_name();
$initials = collect(preg_split('/\s+/', trim($name)))
    ->filter()
    ->map(fn ($w) => mb_substr($w, 0, 1))
    ->take(3)
    ->implode('');
$initials = mb_strtoupper($initials ?: 'S');
@endphp

<svg {{ $attributes->merge(['class' => 'h-8 w-auto', 'viewBox' => '0 0 220 44', 'fill' => 'none', 'xmlns' => 'http://www.w3.org/2000/svg']) }} aria-label="{{ $name }}">
    <rect x="0" y="0" width="44" height="44" rx="8" fill="#1d4ed8"/>
    <text x="22" y="29" font-family="Inter, system-ui, sans-serif" font-weight="800" font-size="16" fill="white" text-anchor="middle">{{ $initials }}</text>
    <text x="52" y="28" font-family="Inter, system-ui, sans-serif" font-weight="800" font-size="16" fill="#0f172a">{{ $name }}</text>
</svg>
