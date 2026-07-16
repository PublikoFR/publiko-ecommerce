<div class="bg-white border border-neutral-100 rounded-xl">
    <div class="flex items-center h-16 px-6 border-b border-neutral-100">
        <h3 class="text-lg font-medium">
            Paiement
        </h3>
    </div>

    @if ($currentStep >= $step)
        @if ($this->isQuoteOnlyCart)
            <div class="p-6 space-y-4">
                <div class="p-4 text-sm text-amber-800 rounded-lg bg-amber-50 border border-amber-200">
                    Votre commande nécessite un devis transport. Vous recevrez un lien de paiement
                    avec le montant final une fois les frais de port calculés.
                </div>

                @error('checkout')
                    <div class="p-4 text-sm text-red-700 rounded-lg bg-red-50">
                        {{ $message }}
                    </div>
                @enderror

                <form wire:submit="checkout">
                    <button class="px-5 py-3 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-500"
                            type="submit"
                            wire:key="quote_submit_btn">
                        <span wire:loading.remove.delay wire:target="checkout">
                            Demander un devis
                        </span>
                        <span wire:loading.delay wire:target="checkout">
                            <svg class="w-5 h-5 text-white animate-spin"
                                 xmlns="http://www.w3.org/2000/svg"
                                 fill="none"
                                 viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </span>
                    </button>
                </form>
            </div>
        @else
        <div class="p-6 space-y-4">
            @php($sepaEnabled = (bool) ($cart->customer?->sepa_enabled ?? false))
            @if ($sepaEnabled)
                <div class="flex gap-3">
                    <button @class([
                        'px-4 py-2 text-sm border font-medium rounded-lg transition-colors',
                        'text-primary-700 border-primary-600 bg-primary-50' => $paymentType === 'card',
                        'text-neutral-500 border-neutral-200 hover:text-neutral-700' => $paymentType !== 'card',
                    ])
                            type="button"
                            wire:click.prevent="$set('paymentType', 'card')">
                        Carte bancaire
                    </button>
                    <button @class([
                        'px-4 py-2 text-sm border font-medium rounded-lg transition-colors',
                        'text-primary-700 border-primary-600 bg-primary-50' => $paymentType === 'sepa',
                        'text-neutral-500 border-neutral-200 hover:text-neutral-700' => $paymentType !== 'sepa',
                    ])
                            type="button"
                            wire:click.prevent="$set('paymentType', 'sepa')">
                        Prélèvement SEPA
                    </button>
                </div>
            @endif

            @if ($paymentType === 'card')
                <livewire:stripe.payment :cart="$cart"
                                         :returnUrl="route('checkout.view', ['paymentType' => 'card'])" />
            @elseif ($paymentType === 'sepa')
                <livewire:sepa-payment-form :cart="$cart"
                                             :returnUrl="route('checkout.view', ['paymentType' => 'sepa'])" />
            @endif
        </div>
        @endif
    @endif
</div>
