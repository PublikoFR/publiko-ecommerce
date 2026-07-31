<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>Demo Storefront</title>
    <meta
        name="description"
        content="Example of an ecommerce storefront built with Lunar."
    >
    <link
        rel="icon"
        href="{{ brand_favicon() ?? asset('favicon.svg') }}"
    >
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stripeScripts
    {{-- Ce layout n'exposait aucune pile : tout @push('styles')/@push('scripts')
         d'un composant rendu sur la page de commande partait à la poubelle
         silencieusement (constaté sur la CDN Leaflet de la carte des points relais). --}}
    @stack('styles')
</head>

<body class="antialiased text-gray-900">
    <x-storefront.maintenance-banner />
    <header class="relative border-b border-gray-100">
        <div class="flex items-center h-16 px-4 mx-auto max-w-screen-2xl sm:px-6 lg:px-8">
            <a
                class="flex items-center flex-shrink-0"
                href="{{ url('/') }}"
            >
                <span class="sr-only">Home</span>

                <x-brand.logo class="w-auto h-6 text-indigo-600" />
            </a>
        </div>
    </header>


    <main>
        {{ $slot }}
    </main>

    <x-footer />

    @livewireScripts
    @stack('scripts')
</body>

</html>
