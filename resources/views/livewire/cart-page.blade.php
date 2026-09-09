<section class="py-8 md:py-12">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-ui.breadcrumb :items="[['label' => 'Mon panier']]" class="mb-4" />
        <h1 class="font-display font-bold text-2xl md:text-3xl text-neutral-900 mb-8">Mon panier</h1>

        @if ($this->hasMixedCart)
            <div class="mb-6 flex items-start gap-3 p-4 text-sm text-amber-800 rounded-lg bg-amber-50 border border-amber-200">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <span>
                    Votre panier contient
                    <strong>{{ $this->quoteLineCount }}&nbsp;article{{ $this->quoteLineCount > 1 ? 's' : '' }} nécessitant un devis transport</strong>.
                    Vous pouvez commander et payer les autres articles dès maintenant&nbsp;;
                    un devis vous sera envoyé séparément pour les articles concernés.
                </span>
            </div>
        @endif

        @if (empty($lines))
            <x-ui.card padding="lg" class="text-center py-16">
                <x-ui.icon name="package" class="w-16 h-16 text-neutral-300 mx-auto mb-4" strokeWidth="1.4" />
                <h2 class="font-display font-bold text-xl text-neutral-900 mb-2">Votre panier est vide</h2>
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
                                    @if (($line['availability']['status'] ?? '') === 'weklo')
                                        <p class="text-xs text-success-700 font-medium mt-1">En stock — expédition 24/48 h</p>
                                    @elseif (($line['availability']['status'] ?? '') === 'supplier')
                                        <p class="text-xs text-warning-700 font-medium mt-1">Disponible sur commande fournisseur
                                            @if (!empty($line['availability']['lead_min']) && !empty($line['availability']['lead_max']))
                                                — Livraison estimée sous {{ $line['availability']['lead_min'] }} à {{ $line['availability']['lead_max'] }} jours ouvrés
                                            @endif
                                        </p>
                                    @endif
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
                    <x-ui.card padding="lg" accent>
                        <h2 class="font-display font-bold text-neutral-900 mb-4">Récapitulatif</h2>
                        @if ($this->cart)
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between"><span class="text-neutral-600">Sous-total HT</span><span class="font-semibold">{{ $this->cart->subTotal?->formatted() ?? '—' }}</span></div>
                                @if (($this->cart->discountTotal?->value ?? 0) > 0)
                                    <div class="flex justify-between text-success-700">
                                        <span>
                                            Remise
                                            @if ($this->cart->coupon_code)
                                                <span class="font-mono">({{ $this->cart->coupon_code }})</span>
                                            @endif
                                        </span>
                                        <span class="font-semibold">-{{ $this->cart->discountTotal->formatted() }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between"><span class="text-neutral-600">TVA</span><span class="font-semibold">{{ $this->cart->taxTotal?->formatted() ?? '—' }}</span></div>
                                <div class="flex justify-between items-baseline pt-3 border-t border-neutral-100"><span class="font-bold">Total TTC</span><span class="font-display font-bold text-2xl text-primary-600">{{ $this->cart->total?->formatted() ?? '—' }}</span></div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-neutral-100">
                                <livewire:components.coupon-code wire:key="cart-coupon" />
                            </div>
                        @endif
                        <x-ui.button variant="accent" size="lg" href="/checkout" fullWidth iconRight="arrow-right" class="mt-6">Passer la commande</x-ui.button>
                        <p class="text-xs text-neutral-500 text-center mt-3">Livraison offerte dès {{ number_format(\Pko\ShippingCommon\Settings\ShippingSettings::thresholdCents() / 100, 0, ',', ' ') }} € HT</p>
                    </x-ui.card>
                </aside>
            </div>
        @endif
    </div>
</section>
