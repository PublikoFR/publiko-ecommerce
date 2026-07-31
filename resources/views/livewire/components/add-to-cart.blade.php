<div @class(['relative' => $compact])>
    @if ($compact)
        {{-- Carte produit : bouton d'ajout compact (qté 1) --}}
        <button type="button" wire:click.prevent="addToCart"
                class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 text-xs font-semibold text-primary-700 bg-accent-500 rounded-md hover:bg-accent-600 shadow-accent transition active:scale-[0.97] whitespace-nowrap"
                aria-label="Ajouter au panier">
            <x-ui.icon name="plus" class="w-4 h-4" />
            Ajouter
        </button>
    @else
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
    @endif

    @if ($errors->has('quantity'))
        {{-- En carte produit, le bouton vit dans une colonne étroite (`shrink-0`) alignée
             en bas avec le prix : un bloc d'erreur en flux y élargit la colonne et vient
             recouvrir le prix. On le sort donc du flux, ancré AU-DESSUS du bouton —
             l'`<article>` de la carte est en `overflow-hidden`, un ancrage vers le bas
             serait rogné. En page produit (mode normal), le bloc reste en flux. --}}
        <div @class([
            'text-xs font-medium text-danger-700 rounded-md bg-danger-50 border border-danger-100',
            'p-2.5 mt-3 text-center' => ! $compact,
            'absolute bottom-full right-0 mb-1.5 z-20 w-max max-w-[13rem] px-2.5 py-1.5 text-right shadow-sm' => $compact,
        ]) role="alert">
            @foreach ($errors->get('quantity') as $error)
                {{ $error }}
            @endforeach
        </div>
    @endif
</div>
