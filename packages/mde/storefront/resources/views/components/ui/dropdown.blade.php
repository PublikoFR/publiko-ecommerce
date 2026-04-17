@props(['align' => 'right', 'width' => 'w-56'])

@php
$alignment = $align === 'left' ? 'origin-top-left left-0' : 'origin-top-right right-0';
@endphp

<div class="relative" x-data="{ open: false }" @click.away="open = false" @keydown.escape.window="open = false">
    <div @click="open = !open">
        {{ $trigger }}
    </div>
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 mt-2 {{ $width }} {{ $alignment }} rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-none"
        style="display: none;"
    >
        <div class="py-1">{{ $slot }}</div>
    </div>
</div>
