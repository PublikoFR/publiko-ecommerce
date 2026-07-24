@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'id' => null,
    /** Liste des options : tableau associatif [valeur => libellé] ou collection équivalente. */
    'options' => [],
    'placeholder' => '— Sélectionnez —',
    'searchPlaceholder' => 'Rechercher…',
    'emptyText' => 'Aucun résultat',
])

@php
// L'id doit rester stable entre deux rendus Livewire (cf. x-ui.input) : on le
// dérive du wire:model, sinon du label.
$wireModel = $attributes->wire('model')->value();
$id = $id ?? ($wireModel
    ? 'ssel-'.\Illuminate\Support\Str::slug((string) $wireModel)
    : ($label ? 'ssel-'.\Illuminate\Support\Str::slug($label) : 'ssel-'.bin2hex(random_bytes(4))));
$hasError = (bool) $error;

$normalizedOptions = collect($options)
    ->map(fn ($labelValue, $value) => ['value' => (string) $value, 'label' => (string) $labelValue])
    ->values()
    ->all();
@endphp

<div class="w-full">
    @if ($label)
        <label for="{{ $id }}-button" class="block text-sm font-medium text-neutral-700 mb-1.5">{{ $label }}</label>
    @endif

    <div
        x-data="{
            open: false,
            search: '',
            highlighted: 0,
            options: @js($normalizedOptions),
            @if ($wireModel) selected: @entangle($wireModel), @else selected: null, @endif
            normalize(value) {
                return String(value ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
            },
            get filtered() {
                if (this.search === '') return this.options;
                const needle = this.normalize(this.search);

                return this.options.filter((option) => this.normalize(option.label).includes(needle));
            },
            get selectedLabel() {
                const match = this.options.find((option) => option.value === String(this.selected ?? ''));

                return match ? match.label : null;
            },
            openList() {
                this.open = true;
                this.search = '';
                this.highlighted = 0;
                this.$nextTick(() => this.$refs.search?.focus());
            },
            close() {
                this.open = false;
                this.$refs.button?.focus();
            },
            choose(option) {
                this.selected = option ? option.value : null;
                this.open = false;
                this.$refs.button?.focus();
            },
            move(delta) {
                const count = this.filtered.length;
                if (count === 0) return;
                this.highlighted = (this.highlighted + delta + count) % count;
            },
        }"
        x-on:click.outside="open = false"
        x-on:keydown.escape.stop="open && close()"
        class="relative"
    >
        <button
            type="button"
            id="{{ $id }}-button"
            x-ref="button"
            x-on:click="open ? (open = false) : openList()"
            x-on:keydown.down.prevent="openList()"
            aria-haspopup="listbox"
            x-bind:aria-expanded="open"
            {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:model.blur'])->class('flex w-full items-center justify-between gap-2 rounded-md border bg-white px-3 py-2 text-left text-sm shadow-sm transition focus:outline-none focus:ring-1 focus:border-primary-500 focus:ring-primary-500 '.($hasError ? 'border-danger-500' : 'border-neutral-300')) }}
        >
            <span x-text="selectedLabel ?? @js($placeholder)" x-bind:class="selectedLabel ? 'text-neutral-900' : 'text-neutral-400'" class="truncate"></span>
            <svg class="h-4 w-4 shrink-0 text-neutral-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition.opacity.duration.100ms
            class="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-lg"
        >
            <div class="border-b border-neutral-100 p-2">
                <input
                    type="text"
                    x-ref="search"
                    x-model="search"
                    x-on:input="highlighted = 0"
                    x-on:keydown.down.prevent="move(1)"
                    x-on:keydown.up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="filtered[highlighted] && choose(filtered[highlighted])"
                    placeholder="{{ $searchPlaceholder }}"
                    class="block w-full rounded-md border-neutral-300 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-primary-500"
                    aria-label="{{ $searchPlaceholder }}"
                />
            </div>

            <ul class="max-h-60 overflow-y-auto py-1 text-sm" role="listbox">
                {{-- Option de désélection : affichée seulement quand une valeur est choisie,
                     sinon elle ferait doublon avec le placeholder déjà visible sur le bouton. --}}
                <li x-show="selected" x-cloak>
                    <button type="button" x-on:click="choose(null)" class="block w-full px-3 py-2 text-left text-neutral-500 hover:bg-neutral-50">
                        {{ $placeholder }}
                    </button>
                </li>
                <template x-for="(option, index) in filtered" :key="option.value">
                    <li>
                        <button
                            type="button"
                            role="option"
                            x-bind:aria-selected="option.value === String(selected ?? '')"
                            x-on:click="choose(option)"
                            x-on:mouseenter="highlighted = index"
                            x-bind:class="{
                                'bg-primary-50 text-primary-800': index === highlighted,
                                'font-semibold': option.value === String(selected ?? ''),
                            }"
                            class="block w-full px-3 py-2 text-left text-neutral-700 hover:bg-primary-50"
                            x-text="option.label"
                        ></button>
                    </li>
                </template>
                <li x-show="filtered.length === 0" class="px-3 py-3 text-center text-neutral-500">{{ $emptyText }}</li>
            </ul>
        </div>
    </div>

    @if ($error)
        <p class="mt-1.5 text-sm text-danger-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-sm text-neutral-500">{{ $hint }}</p>
    @endif
</div>
