@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'icon' => null,
    'id' => null,
])

@php
$id = $id ?? ('inp-'.bin2hex(random_bytes(4)));
$hasError = (bool) $error;
$inputClass = 'block w-full rounded-md border-neutral-300 text-neutral-900 placeholder:text-neutral-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm '.($hasError ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500' : '').' '.($icon ? 'pl-10' : '');
@endphp

<div class="w-full">
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-neutral-700 mb-1.5">{{ $label }}</label>
    @endif
    <div class="relative">
        @if ($icon)
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-neutral-400">
                <x-ui.icon :name="$icon" class="w-5 h-5" />
            </div>
        @endif
        <input id="{{ $id }}" {{ $attributes->merge(['type' => 'text'])->class($inputClass) }} />
    </div>
    @if ($error)
        <p class="mt-1.5 text-sm text-danger-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-sm text-neutral-500">{{ $hint }}</p>
    @endif
</div>
