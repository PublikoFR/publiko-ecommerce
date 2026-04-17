<div>
    {{-- Backdrop --}}
    <div
        x-data
        x-show="$wire.open"
        x-transition.opacity.duration.200ms
        class="fixed inset-0 bg-neutral-900/50 z-50 backdrop-blur-sm"
        wire:click="close"
        style="display: none;"
    ></div>

    {{-- Drawer --}}
    <aside
        x-data
        x-show="$wire.open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @keydown.escape.window="$wire.close()"
        class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 flex flex-col"
        style="display: none;"
        aria-label="Panier"
    >
        {{-- Header --}}
        <header class="flex items-center justify-between px-5 py-4 border-b border-neutral-200 bg-primary-700 text-white">
            <div class="flex items-center gap-3">
                <x-ui.icon name="cart" class="w-5 h-5" />
                <h2 class="font-bold text-lg">Mon panier</h2>
                @if ($linesCount > 0)
                    <span class="bg-white/20 text-xs font-bold px-2 py-0.5 rounded-full">{{ $linesCount }}</span>
                @endif
            </div>
            <button type="button" wire:click="close" class="text-white/70 hover:text-white" aria-label="Fermer">
                <x-ui.icon name="close" class="w-6 h-6" />
            </button>
        </header>

        {{-- Body --}}
        @if ($linesCount === 0)
            <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
                <x-ui.icon name="cart" class="w-16 h-16 text-neutral-300 mb-4" />
                <p class="text-neutral-600 font-semibold mb-1">Votre panier est vide</p>
                <p class="text-sm text-neutral-500 mb-6">Ajoutez des produits depuis le catalogue.</p>
                <x-ui.button variant="primary" href="/" wire:click="close">Voir le catalogue</x-ui.button>
            </div>
        @else
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                @foreach ($lines as $line)
                    <div class="flex items-start gap-3 p-3 bg-neutral-50 rounded-lg" wire:key="drawer-line-{{ $line['id'] }}">
                        <div class="w-14 h-14 bg-white border border-neutral-200 rounded flex items-center justify-center shrink-0 p-1">
                            @if ($line['thumbnail'])
                                <img src="{{ $line['thumbnail'] }}" alt="" class="max-w-full max-h-full object-contain" />
                            @else
                                <x-ui.icon name="shopping-bag" class="w-6 h-6 text-neutral-300" />
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-neutral-900 line-clamp-2">{{ $line['description'] }}</p>
                            <p class="text-xs text-neutral-500 mt-0.5">Réf. {{ $line['identifier'] }}</p>
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex items-center gap-1">
                                    <button type="button" wire:click="updateQuantity({{ $line['id'] }}, {{ $line['quantity'] - 1 }})" class="w-7 h-7 border border-neutral-300 rounded text-neutral-600 hover:border-primary-500 hover:text-primary-600 flex items-center justify-center text-sm font-bold">−</button>
                                    <span class="w-8 text-center text-sm font-semibold">{{ $line['quantity'] }}</span>
                                    <button type="button" wire:click="updateQuantity({{ $line['id'] }}, {{ $line['quantity'] + 1 }})" class="w-7 h-7 border border-neutral-300 rounded text-neutral-600 hover:border-primary-500 hover:text-primary-600 flex items-center justify-center text-sm font-bold">+</button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-neutral-900 text-sm">{{ $line['sub_total'] }}</span>
                                    <button type="button" wire:click="removeLine({{ $line['id'] }})" class="text-neutral-400 hover:text-danger-600" aria-label="Retirer">
                                        <x-ui.icon name="trash" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Footer --}}
            <footer class="border-t border-neutral-200 p-5 bg-white space-y-3">
                <div class="space-y-1 text-sm">
                    @if ($subTotal)
                        <div class="flex justify-between text-neutral-600"><span>Sous-total HT</span><span>{{ $subTotal }}</span></div>
                    @endif
                    @if ($total)
                        <div class="flex justify-between font-black text-lg pt-2 border-t border-neutral-100"><span>Total TTC</span><span class="text-primary-700">{{ $total }}</span></div>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-2 pt-2">
                    <x-ui.button variant="secondary" href="/panier" wire:click="close" class="justify-center">Voir le panier</x-ui.button>
                    <x-ui.button variant="primary" href="/checkout" wire:click="close" class="justify-center">Commander →</x-ui.button>
                </div>
            </footer>
        @endif
    </aside>
</div>
