<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'MDE Distribution' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-neutral-50 font-sans antialiased text-neutral-900">
    <div class="min-h-screen flex flex-col">
        <header class="border-b border-neutral-200 bg-white">
            <div class="max-w-screen-xl mx-auto px-4 py-4 flex items-center justify-between">
                <a href="/" class="flex items-center">
                    <x-layout.logo class="h-9 w-auto" />
                </a>
                <div class="text-sm text-neutral-500">
                    <a href="/" class="hover:text-primary-600 transition">← Retour au site</a>
                </div>
            </div>
        </header>

        <main class="flex-1 flex items-center justify-center py-12 px-4">
            <div class="w-full max-w-md">
                @if (session('status'))
                    <div class="mb-6"><x-ui.alert variant="success">{{ session('status') }}</x-ui.alert></div>
                @endif
                {{ $slot }}
            </div>
        </main>

        <footer class="py-6 text-center text-xs text-neutral-500">
            © {{ now()->year }} MDE Distribution — accès réservé aux professionnels.
        </footer>
    </div>
    @livewireScripts
</body>
</html>
