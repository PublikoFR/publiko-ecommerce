@props(['variant' => 'neutral', 'size' => 'sm'])

@php
$variants = [
    'neutral' => 'bg-neutral-100 text-neutral-700',
    'primary' => 'bg-primary-100 text-primary-700',
    'success' => 'bg-success-100 text-success-700',
    'warning' => 'bg-warning-100 text-warning-700',
    'danger' => 'bg-danger-100 text-danger-700',
    'new' => 'bg-amber-500 text-white',
];
$sizes = ['xs' => 'px-1.5 py-0.5 text-[10px]', 'sm' => 'px-2 py-0.5 text-xs', 'md' => 'px-2.5 py-1 text-sm'];
@endphp

<span {{ $attributes->class(['inline-flex items-center font-semibold uppercase tracking-wide rounded', $variants[$variant] ?? $variants['neutral'], $sizes[$size] ?? $sizes['sm']]) }}>
    {{ $slot }}
</span>
