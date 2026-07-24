<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? brand_name() }}</title>
    <link rel="icon" href="{{ brand_favicon() ?? asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Fonts (Hanken Grotesk / IBM Plex Mono) et Forno Waffle sont chargées via resources/css/app.css (Design System) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="min-h-screen flex flex-col bg-neutral-50 text-neutral-900 font-sans antialiased">
    <x-layout.lateral-menu />
    <x-layout.header />

    @auth
        @php
            // Le bandeau de vérification e-mail ne concerne QUE les comptes encore
            // `pending` : pour eux, valider l'e-mail est l'étape qui active le compte.
            // Un compte déjà `active` (activé en back-office, e-mail non « vérifié »
            // au sens Laravel) ne doit pas être nagué — c'était le bug.
            $__pkoStatus = auth()->user()->customers()->first()?->getAttribute('pko_status');
        @endphp
        @if (! auth()->user()->hasVerifiedEmail() && $__pkoStatus === 'pending')
            <div class="bg-warning-50 border-b border-warning-200 text-warning-900">
                <div class="max-w-7xl mx-auto px-4 py-2.5 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-sm">
                    <span>Pensez à <strong>vérifier votre adresse e-mail</strong> pour sécuriser votre compte.</span>
                    <form method="POST" action="{{ route('verification.send') }}" class="inline">
                        @csrf
                        <button type="submit" class="font-semibold underline hover:no-underline">Renvoyer le lien</button>
                    </form>
                </div>
            </div>
        @endif
    @endauth

    <main class="flex-1">{{ $slot }}</main>
    <x-layout.footer />
    @auth
        @livewire('storefront.cart-drawer')
        @livewire('purchase-lists.picker')
    @endauth
    @livewireScripts
    @stack('scripts')
</body>
</html>
