<div>
    @if ($this->appliedCode)
        <div class="flex items-center justify-between gap-3 rounded-md border border-success-200 bg-success-50 px-3 py-2.5">
            <div class="min-w-0 flex items-start gap-2">
                <x-ui.icon name="percent" class="w-4 h-4 mt-0.5 text-success-700 shrink-0" />
                <div class="min-w-0">
                    <p class="text-xs font-medium text-success-800">Code promo appliqué</p>
                    <p class="font-mono text-sm font-semibold text-success-900 truncate">{{ $this->appliedCode }}</p>
                </div>
            </div>
            <button
                type="button"
                wire:click="remove"
                wire:loading.attr="disabled"
                class="text-sm font-semibold text-success-800 hover:text-danger-600 shrink-0 disabled:opacity-50"
            >
                Retirer
            </button>
        </div>
    @else
        <form wire:submit="apply" class="space-y-1.5">
            <label for="inp-coupon-code" class="block text-sm font-medium text-neutral-700">Code promo</label>
            <div class="flex gap-2">
                <input
                    id="inp-coupon-code"
                    type="text"
                    wire:model="code"
                    maxlength="40"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    placeholder="Votre code"
                    class="block w-full rounded-md border-neutral-300 text-neutral-900 placeholder:text-neutral-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm @error('code') border-danger-500 focus:border-danger-500 focus:ring-danger-500 @enderror"
                />
                <x-ui.button type="submit" variant="secondary" size="sm">
                    Appliquer
                </x-ui.button>
            </div>
            @error('code')
                <p class="text-sm text-danger-600">{{ $message }}</p>
            @enderror
        </form>
    @endif
</div>
