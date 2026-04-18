@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'id' => null,
])

@php
$id = $id ?? ('sel-'.bin2hex(random_bytes(4)));
$hasError = (bool) $error;
$selectClass = 'block w-full rounded-md border-neutral-300 text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm '.($hasError ? 'border-danger-500' : '');
@endphp

<div class="w-full">
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-neutral-700 mb-1.5">{{ $label }}</label>
    @endif
    <select id="{{ $id }}" {{ $attributes->class($selectClass) }}>
        {{ $slot }}
    </select>
    @if ($error)
        <p class="mt-1.5 text-sm text-danger-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-sm text-neutral-500">{{ $hint }}</p>
    @endif
</div>
