<div>
    <div class="flex gap-3" x-data="{ qty: @entangle('quantity').live }">
        {{-- Stepper quantité (Design System) --}}
        <div class="inline-flex items-center h-12 border-[1.5px] border-neutral-300 rounded-md shrink-0">
            <button type="button" @click="qty = Math.max(1, (parseInt(qty) || 1) - 1)"
                    class="w-10 h-full flex items-center justify-center text-primary-600 hover:bg-neutral-50 rounded-l-md transition" aria-label="Diminuer">
                <x-ui.icon name="minus" class="w-4 h-4" />
            </button>
            <label for="quantity" class="sr-only">Quantité</label>
            <input id="quantity" type="number" min="1" x-model.number="qty"
                   class="w-12 h-full text-center border-0 bg-transparent focus:ring-0 font-mono font-semibold text-neutral-900 no-spinner" />
            <button type="button" @click="qty = (parseInt(qty) || 1) + 1"
                    class="w-10 h-full flex items-center justify-center text-primary-600 hover:bg-neutral-50 rounded-r-md transition" aria-label="Augmenter">
                <x-ui.icon name="plus" class="w-4 h-4" />
            </button>
        </div>

        <button type="submit" wire:click.prevent="addToCart"
                class="flex-1 inline-flex items-center justify-center gap-2 h-12 px-6 text-sm font-semibold text-primary-700 bg-accent-500 rounded-md shadow-accent hover:bg-accent-600 transition active:scale-[0.97]">
            <x-ui.icon name="cart" class="w-5 h-5" />
            Ajouter au panier
        </button>
    </div>

    @if ($errors->has('quantity'))
        <div class="p-2.5 mt-3 text-xs font-medium text-center text-danger-700 rounded-md bg-danger-50 border border-danger-100" role="alert">
            @foreach ($errors->get('quantity') as $error)
                {{ $error }}
            @endforeach
        </div>
    @endif
</div>
