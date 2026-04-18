@props(['href' => null, 'icon' => null])

@php
$class = 'flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 hover:text-primary-700 transition';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($class) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="w-4 h-4 text-neutral-400" />@endif
        {{ $slot }}
    </a>
@else
    <button type="button" {{ $attributes->class($class.' w-full text-left') }}>
        @if ($icon)<x-ui.icon :name="$icon" class="w-4 h-4 text-neutral-400" />@endif
        {{ $slot }}
    </button>
@endif
