@props(['variant' => 'info', 'title' => null])

@php
$variants = [
    'info' => ['border-primary-200 bg-primary-50 text-primary-800', 'info'],
    'success' => ['border-success-200 bg-success-50 text-success-800', 'check'],
    'warning' => ['border-warning-200 bg-warning-50 text-warning-800', 'warning'],
    'danger' => ['border-danger-200 bg-danger-50 text-danger-800', 'warning'],
];
[$classes, $icon] = $variants[$variant] ?? $variants['info'];
@endphp

<div {{ $attributes->class(['border rounded-md p-4 flex gap-3', $classes]) }} role="alert">
    <x-ui.icon :name="$icon" class="w-5 h-5 shrink-0 mt-0.5" />
    <div class="flex-1 text-sm">
        @if ($title)<p class="font-semibold mb-1">{{ $title }}</p>@endif
        <div>{{ $slot }}</div>
    </div>
</div>
