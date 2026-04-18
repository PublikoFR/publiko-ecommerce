@props(['name', 'maxWidth' => 'md', 'title' => null])

@php
$widths = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl'];
@endphp

<div
    x-data="{ show: false }"
    x-on:open-modal-{{ $name }}.window="show = true"
    x-on:close-modal-{{ $name }}.window="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    class="fixed inset-0 z-50 overflow-y-auto"
    style="display: none;"
>
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="show" x-transition.opacity class="fixed inset-0 bg-neutral-900/60" @click="show = false"></div>
        <div
            x-show="show"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            class="relative bg-white rounded-lg shadow-xl w-full {{ $widths[$maxWidth] ?? $widths['md'] }}"
        >
            @if ($title)
                <div class="flex items-center justify-between px-6 py-4 border-b border-neutral-200">
                    <h2 class="text-lg font-semibold text-neutral-900">{{ $title }}</h2>
                    <button type="button" class="text-neutral-400 hover:text-neutral-600" @click="show = false">
                        <x-ui.icon name="close" class="w-5 h-5" />
                    </button>
                </div>
            @endif
            <div class="p-6">{{ $slot }}</div>
        </div>
    </div>
</div>
