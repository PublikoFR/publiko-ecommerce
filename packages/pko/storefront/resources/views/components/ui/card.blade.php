@props(['padding' => 'md', 'hover' => false])

@php
$paddings = ['none' => '', 'sm' => 'p-3', 'md' => 'p-5', 'lg' => 'p-7'];
$base = 'bg-white border border-neutral-200 rounded-lg shadow-sm';
$hoverClass = $hover ? 'transition hover:shadow-md hover:border-neutral-300' : '';
@endphp

<div {{ $attributes->class([$base, $paddings[$padding] ?? $paddings['md'], $hoverClass]) }}>
    {{ $slot }}
</div>
