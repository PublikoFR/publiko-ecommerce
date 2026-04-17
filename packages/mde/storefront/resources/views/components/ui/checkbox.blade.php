@props(['label' => null, 'id' => null, 'hint' => null])

@php
$id = $id ?? ('cb-'.bin2hex(random_bytes(4)));
@endphp

<label for="{{ $id }}" class="inline-flex items-start gap-2.5 cursor-pointer">
    <input type="checkbox" id="{{ $id }}" {{ $attributes->class('mt-0.5 rounded border-neutral-300 text-primary-600 focus:ring-primary-500 h-4 w-4') }} />
    <span class="text-sm text-neutral-700 leading-snug">
        {{ $label ?? $slot }}
        @if ($hint)<span class="block text-xs text-neutral-500 mt-0.5">{{ $hint }}</span>@endif
    </span>
</label>
