@props(['color' => 'primary', 'onRemove' => null])

@php
    $colors = [
        'primary' => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
        'gray' => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-200',
    ];
    $classes = $colors[$color] ?? $colors['gray'];
@endphp

<span
    {{ $attributes->merge([
        'class' => 'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-md ' . $classes,
    ]) }}
>
    {{ $slot }}
    @if ($onRemove)
        <button type="button" wire:click="{{ $onRemove }}" class="opacity-60 hover:opacity-100">
            &times;
        </button>
    @endif
</span>
