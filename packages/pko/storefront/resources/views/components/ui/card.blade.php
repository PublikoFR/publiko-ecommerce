@props([
    'padding' => 'md',
    'elevation' => 'sm',
    'interactive' => false,
    'hover' => false,     // alias rétro-compat de interactive
    'accent' => false,    // barre lime en haut (Design System)
])

@php
$paddings = ['none' => '', 'sm' => 'p-3', 'md' => 'p-5', 'lg' => 'p-7'];
$elevations = ['none' => '', 'sm' => 'shadow-sm', 'md' => 'shadow-md', 'lg' => 'shadow-lg'];
$isInteractive = $interactive || $hover;
$base = 'relative bg-white border border-neutral-200 rounded-xl';
$interactiveClass = $isInteractive
    ? 'transition duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:border-neutral-300'
    : '';
@endphp

<div {{ $attributes->class([$base, $elevations[$elevation] ?? $elevations['sm'], $paddings[$padding] ?? $paddings['md'], $interactiveClass]) }}>
    @if ($accent)
        <span class="absolute inset-x-0 top-0 h-1 rounded-t-xl bg-accent-500"></span>
    @endif
    {{ $slot }}
</div>
