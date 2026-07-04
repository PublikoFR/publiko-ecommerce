@php
$usps = config('storefront.usps', []);
@endphp

@if (! empty($usps))
    <div class="border-b border-white/10">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-7 grid grid-cols-2 md:grid-cols-4 gap-6">
            @foreach ($usps as $usp)
                <div class="flex items-center gap-3">
                    <span class="shrink-0 w-11 h-11 rounded-md bg-accent-500/15 flex items-center justify-center text-accent-400">
                        <x-ui.icon :name="$usp['icon'] ?? 'badge-check'" class="w-[22px] h-[22px]" />
                    </span>
                    <div>
                        <p class="font-bold text-white text-sm leading-tight">{{ $usp['title'] }}</p>
                        <p class="text-xs text-neutral-300 mt-0.5">{{ $usp['subtitle'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
