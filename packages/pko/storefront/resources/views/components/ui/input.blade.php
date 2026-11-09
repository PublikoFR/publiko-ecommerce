@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'icon' => null,
    'id' => null,
    'trailing' => null,
    'labelTrailing' => null,
])

@php
// L'id DOIT être stable entre deux rendus Livewire : un id régénéré à chaque
// render (bin2hex/random) casse le morphing → les inputs liés en wire:model ne
// reçoivent jamais les valeurs mises à jour côté serveur (ex. préremplissage
// SIRET). On le dérive donc du nom du wire:model, sinon du label.
$wireModel = $attributes->wire('model')->value();
$id = $id ?? ($wireModel
    ? 'inp-'.\Illuminate\Support\Str::slug((string) $wireModel)
    : ($label ? 'inp-'.\Illuminate\Support\Str::slug($label) : 'inp-'.bin2hex(random_bytes(4))));
$hasError = (bool) $error;
$hasTrailing = ! is_null($trailing);
// Champ mot de passe : œil afficher/masquer intégré (Alpine). Le type rendu côté
// serveur reste « password » : masqué sans JS et après un morphing Livewire.
$isPassword = $attributes->get('type') === 'password';
$isRequired = $attributes->has('required');
$paddingRight = match (true) {
    $isPassword && $hasTrailing => 'pr-20',
    $isPassword, $hasTrailing => 'pr-10',
    default => '',
};
$inputClass = 'block w-full rounded-md border-neutral-300 text-neutral-900 placeholder:text-neutral-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm '.($hasError ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500' : '').' '.($icon ? 'pl-10' : '').' '.$paddingRight;
$inputAttributes = $attributes->merge(['type' => 'text'])->class($inputClass);
if ($isPassword) {
    $inputAttributes = $inputAttributes->merge(['x-bind:type' => "revealed ? 'text' : 'password'"]);
}
@endphp

<div class="w-full">
    @if ($label)
        <div class="flex items-end justify-between gap-2 mb-1.5">
            <label for="{{ $id }}" class="block text-sm font-medium text-neutral-700">{{ $label }}@if ($isRequired)<span class="text-danger-500"> *</span>@endif</label>
            @if (! is_null($labelTrailing))
                <div class="shrink-0">{{ $labelTrailing }}</div>
            @endif
        </div>
    @endif
    <div class="relative" @if ($isPassword) x-data="{ revealed: false }" @endif>
        @if ($icon)
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-neutral-400">
                <x-ui.icon :name="$icon" class="w-5 h-5" />
            </div>
        @endif
        <input id="{{ $id }}" {{ $inputAttributes }} />
        @if ($hasTrailing || $isPassword)
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center gap-2">
                @if ($hasTrailing)
                    {{ $trailing }}
                @endif
                @if ($isPassword)
                    <button
                        type="button"
                        data-password-toggle
                        aria-controls="{{ $id }}"
                        aria-label="Afficher le mot de passe"
                        aria-pressed="false"
                        x-on:click="revealed = ! revealed"
                        x-bind:aria-label="revealed ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                        x-bind:aria-pressed="revealed ? 'true' : 'false'"
                        class="flex items-center rounded-md text-neutral-400 transition-colors hover:text-neutral-600 focus:outline-none focus-visible:text-neutral-700 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1"
                    >
                        <x-ui.icon name="eye" class="w-5 h-5" x-show="! revealed" />
                        <x-ui.icon name="eye-off" class="w-5 h-5" x-show="revealed" x-cloak />
                    </button>
                @endif
            </div>
        @endif
    </div>
    @if ($error)
        <p class="mt-1.5 text-sm text-danger-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-sm text-neutral-500">{{ $hint }}</p>
    @endif
</div>
