@php
$usps = config('mde-storefront.usps', []);
@endphp

@if (! empty($usps))
    <div class="bg-neutral-800 border-b border-neutral-700">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 grid grid-cols-2 md:grid-cols-4 gap-6">
            @foreach ($usps as $usp)
                <div class="flex items-start gap-3 text-white">
                    <div class="shrink-0 w-10 h-10 rounded-full bg-primary-600/20 flex items-center justify-center text-primary-300">
                        <x-ui.icon :name="$usp['icon'] ?? 'check'" class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="font-bold text-sm leading-tight">{{ $usp['title'] }}</p>
                        <p class="text-xs text-neutral-400 mt-0.5">{{ $usp['subtitle'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
