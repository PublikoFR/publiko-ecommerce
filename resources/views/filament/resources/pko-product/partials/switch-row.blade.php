@props(['label', 'description' => null, 'model'])

<label class="flex items-start justify-between gap-4 py-2 cursor-pointer">
    <div class="flex-1 min-w-0">
        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $label }}</div>
        @if ($description)
            <div class="text-xs text-gray-500 mt-0.5">{{ $description }}</div>
        @endif
    </div>
    <button
        type="button"
        x-data
        x-on:click.prevent="$wire.set(@js($model), ! $wire.get(@js($model)))"
        x-bind:class="$wire.get(@js($model)) ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-600'"
        class="relative inline-flex shrink-0 h-5 w-9 rounded-full transition-colors"
    >
        <span
            class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
            x-bind:class="$wire.get(@js($model)) ? 'translate-x-4' : 'translate-x-0'"
        ></span>
    </button>
</label>
