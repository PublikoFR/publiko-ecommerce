@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'loading' => false,
])

@php
$variants = [
    'primary' => 'bg-primary-600 text-white hover:bg-primary-700 focus-visible:ring-primary-500 shadow-sm',
    'secondary' => 'bg-white text-neutral-800 border border-neutral-300 hover:bg-neutral-50 focus-visible:ring-primary-500',
    'ghost' => 'text-neutral-700 hover:bg-neutral-100 focus-visible:ring-primary-500',
    'danger' => 'bg-danger-600 text-white hover:bg-danger-700 focus-visible:ring-danger-500 shadow-sm',
    'success' => 'bg-success-600 text-white hover:bg-success-700 focus-visible:ring-success-500 shadow-sm',
    'link' => 'text-primary-600 hover:text-primary-700 hover:underline px-0',
    'outline' => 'border border-primary-600 text-primary-600 hover:bg-primary-50 focus-visible:ring-primary-500',
];

$sizes = [
    'xs' => 'px-2 py-1 text-xs gap-1 rounded',
    'sm' => 'px-3 py-1.5 text-sm gap-1.5 rounded-md',
    'md' => 'px-4 py-2 text-sm gap-2 rounded-md',
    'lg' => 'px-5 py-2.5 text-base gap-2 rounded-md',
    'xl' => 'px-6 py-3 text-lg gap-2.5 rounded-lg',
];

$base = 'inline-flex items-center justify-center font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap';
$classes = trim($base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="w-4 h-4 shrink-0" />@endif
        {{ $slot }}
        @if ($iconRight)<x-ui.icon :name="$iconRight" class="w-4 h-4 shrink-0" />@endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }} @if ($loading) disabled wire:loading.attr="disabled" @endif>
        @if ($loading)
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
        @elseif ($icon)
            <x-ui.icon :name="$icon" class="w-4 h-4 shrink-0" />
        @endif
        {{ $slot }}
        @if ($iconRight)<x-ui.icon :name="$iconRight" class="w-4 h-4 shrink-0" />@endif
    </button>
@endif
