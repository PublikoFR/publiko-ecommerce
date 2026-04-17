<section class="py-8 md:py-12">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-ui.breadcrumb :items="[['label' => 'Mon panier']]" class="mb-4" />
        <h1 class="text-2xl md:text-3xl font-black text-neutral-900 mb-8">Mon panier</h1>

        @if (empty($lines))
            <x-ui.card padding="lg" class="text-center py-16">
                <x-ui.icon name="cart" class="w-16 h-16 text-neutral-300 mx-auto mb-4" />
                <h2 class="text-xl font-bold text-neutral-900 mb-2">Votre panier est vide</h2>
                <p class="text-neutral-500 mb-6">Parcourez notre catalogue pour ajouter des produits.</p>
                <x-ui.button variant="primary" href="/" size="lg">Retour au catalogue</x-ui.button>
            </x-ui.card>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-8">
                <x-ui.card padding="none">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-neutral-100">
                        <h2 class="font-bold text-neutral-900">{{ count($lines) }} article{{ count($lines) > 1 ? 's' : '' }}</h2>
                        <button type="button" wire:click="clear" wire:confirm="Vider le panier ?" class="text-sm text-neutral-500 hover:text-danger-600 font-semibold">Vider le panier</button>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($lines as $line)
                            <li class="p-5 flex items-center gap-4" wire:key="line-{{ $line['id'] }}">
                                <div class="w-16 h-16 bg-neutral-50 border border-neutral-100 rounded flex items-center justify-center shrink-0 p-2">
                                    @if ($line['thumbnail'])
                                        <img src="{{ $line['thumbnail'] }}" alt="" class="max-w-full max-h-full object-contain" />
                                    @else
                                        <x-ui.icon name="shopping-bag" class="w-6 h-6 text-neutral-300" />
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-neutral-900">{{ $line['description'] }}</p>
                                    <p class="text-xs text-neutral-500 mt-0.5">Réf. {{ $line['identifier'] }}</p>
                                    <p class="text-xs text-neutral-500">{{ $line['unit_price'] }} / unité</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button" class="w-8 h-8 border border-neutral-300 rounded text-neutral-600 hover:border-primary-500 hover:text-primary-600" wire:click="updateQuantity({{ $line['id'] }}, {{ $line['quantity'] - 1 }})"><x-ui.icon name="minus" class="w-3 h-3 mx-auto" /></button>
                                    <span class="w-10 text-center font-semibold">{{ $line['quantity'] }}</span>
                                    <button type="button" class="w-8 h-8 border border-neutral-300 rounded text-neutral-600 hover:border-primary-500 hover:text-primary-600" wire:click="updateQuantity({{ $line['id'] }}, {{ $line['quantity'] + 1 }})"><x-ui.icon name="plus" class="w-3 h-3 mx-auto" /></button>
                                </div>
                                <div class="w-24 text-right font-bold text-neutral-900 shrink-0">{{ $line['sub_total'] }}</div>
                                <button type="button" class="text-neutral-400 hover:text-danger-600 shrink-0" wire:click="remove({{ $line['id'] }})" title="Retirer"><x-ui.icon name="trash" class="w-4 h-4" /></button>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>

                <aside class="lg:sticky lg:top-28 lg:self-start">
                    <x-ui.card padding="lg">
                        <h2 class="font-bold text-neutral-900 mb-4">Récapitulatif</h2>
                        @if ($this->cart)
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between"><span class="text-neutral-600">Sous-total HT</span><span class="font-semibold">{{ $this->cart->subTotal?->formatted() ?? '—' }}</span></div>
                                <div class="flex justify-between"><span class="text-neutral-600">TVA</span><span class="font-semibold">{{ $this->cart->taxTotal?->formatted() ?? '—' }}</span></div>
                                <div class="flex justify-between text-lg font-black pt-3 border-t border-neutral-100"><span>Total TTC</span><span class="text-primary-700">{{ $this->cart->total?->formatted() ?? '—' }}</span></div>
                            </div>
                        @endif
                        <x-ui.button variant="primary" size="lg" href="/checkout" class="w-full justify-center mt-6">Passer la commande →</x-ui.button>
                        <p class="text-xs text-neutral-500 text-center mt-3">Livraison offerte dès {{ number_format(config('mde-storefront.shipping.free_threshold_cents', 12500) / 100, 0, ',', ' ') }} € HT</p>
                    </x-ui.card>
                </aside>
            </div>
        @endif
    </div>
</section>
