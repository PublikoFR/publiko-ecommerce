<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', brand_name().(brand_tagline() ? ' — '.brand_tagline() : ''))</title>
    <meta name="description" content="@yield('description', brand_meta_description())">

    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="min-h-screen flex flex-col bg-neutral-50 text-neutral-900 font-sans antialiased">
    <x-layout.header />

    <main class="flex-1">
        {{ $slot }}
    </main>

    <x-layout.footer />

    @auth
        @livewire('storefront.cart-drawer')
    @endauth

    @livewireScripts
    @stack('scripts')
</body>
</html>
