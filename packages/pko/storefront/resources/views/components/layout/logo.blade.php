@props([])
@php
$name = brand_name();
$logo = brand_logo();
$initials = collect(preg_split('/\s+/', trim($name)))
    ->filter()
    ->map(fn ($w) => mb_substr($w, 0, 1))
    ->take(2)
    ->implode('');
$initials = mb_strtoupper($initials ?: 'S');
@endphp

@if ($logo)
    {{-- Logo de la boutique (Setting brand.logo — Storefront → Paramètres) --}}
    <img src="{{ $logo }}" alt="{{ $name }}" {{ $attributes->merge(['class' => 'h-9 w-auto']) }} />
@else
    {{-- Logo textuel de repli (Design System : forest + lime, formes arrondies) --}}
    <svg {{ $attributes->merge(['class' => 'h-8 w-auto', 'viewBox' => '0 0 220 44', 'fill' => 'none', 'xmlns' => 'http://www.w3.org/2000/svg']) }} aria-label="{{ $name }}">
        <rect x="0" y="0" width="44" height="44" rx="12" fill="#00453e"/>
        <text x="22" y="29" font-family="'Forno Waffle', 'Hanken Grotesk', system-ui, sans-serif" font-weight="700" font-size="16" fill="#aac932" text-anchor="middle">{{ $initials }}</text>
        <text x="54" y="28" font-family="'Forno Waffle', 'Hanken Grotesk', system-ui, sans-serif" font-weight="700" font-size="17" fill="#00453e">{{ $name }}</text>
    </svg>
@endif
