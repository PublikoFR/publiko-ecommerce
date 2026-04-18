@props(['title' => null, 'icon' => null, 'hint' => null])

<section
    {{ $attributes->merge([
        'class' => 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-md shadow-[0_1px_2px_rgba(0,0,0,0.04)]',
    ]) }}
>
    @if ($title)
        <header class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 dark:border-white/10">
            <div class="flex items-center gap-2">
                @if ($icon)
                    <x-filament::icon :icon="$icon" class="w-4 h-4 text-gray-500" />
                @endif
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
            </div>
            @if ($hint)
                <span class="text-xs text-gray-500">{{ $hint }}</span>
            @endif
        </header>
    @endif

    <div class="p-[18px] space-y-[14px]">
        {{ $slot }}
    </div>
</section>
