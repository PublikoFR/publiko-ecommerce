<div class="bg-white border border-neutral-100 rounded-xl">
    <div class="flex items-center h-16 px-6 border-b border-neutral-100">
        <h3 class="text-lg font-medium">
            Paiement
        </h3>
    </div>

    @if ($currentStep >= $step)
        @if ($this->isQuoteOnlyCart)
            {{-- ── Branche 1 : 100 % devis ────────────────────────────────── --}}
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

        @elseif ($this->isMixedCart && ! $splitConfirmed)
            {{-- ── Branche 2 : panier mixte — choix avant paiement ─────────── --}}
            <div class="p-6 space-y-5">
                <p class="text-sm text-neutral-600">
                    Votre panier contient
                    <strong>{{ $this->quoteLineCount }}&nbsp;article{{ $this->quoteLineCount > 1 ? 's' : '' }}
                    nécessitant un devis transport</strong>.
                    Choisissez comment traiter votre commande&nbsp;:
                </p>

                @error('splitMode')
                    <div class="p-4 text-sm text-red-700 rounded-lg bg-red-50 border border-red-200">
                        {{ $message }}
                    </div>
                @enderror

                <fieldset class="space-y-3">
                    <label class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer transition-colors
                                  @if($splitMode === 'split') border-primary-500 bg-primary-50 @else border-neutral-200 hover:border-neutral-300 @endif">
                        <input type="radio"
                               wire:model.live="splitMode"
                               value="split"
                               class="mt-0.5 text-primary-600 border-neutral-300 focus:ring-primary-500">
                        <div>
                            <p class="text-sm font-medium text-neutral-900">
                                Commander et payer maintenant les articles disponibles
                            </p>
                            <p class="text-xs text-neutral-500 mt-0.5">
                                Un devis transport vous sera envoyé séparément pour les autres articles.
                            </p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer transition-colors
                                  @if($splitMode === 'quote_all') border-amber-500 bg-amber-50 @else border-neutral-200 hover:border-neutral-300 @endif">
                        <input type="radio"
                               wire:model.live="splitMode"
                               value="quote_all"
                               class="mt-0.5 text-amber-600 border-neutral-300 focus:ring-amber-500">
                        <div>
                            <p class="text-sm font-medium text-neutral-900">
                                Tout regrouper en une seule demande de devis
                            </p>
                            <p class="text-xs text-neutral-500 mt-0.5">
                                Vous recevrez un seul devis avec l'ensemble de votre commande.
                            </p>
                        </div>
                    </label>
                </fieldset>

                <div>
                    <button type="button"
                            wire:click="confirmSplitChoice"
                            wire:loading.attr="disabled"
                            wire:target="confirmSplitChoice"
                            class="px-5 py-3 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-500 disabled:opacity-60">
                        <span wire:loading.remove wire:target="confirmSplitChoice">Confirmer mon choix</span>
                        <span wire:loading wire:target="confirmSplitChoice">
                            <svg class="inline w-4 h-4 text-white animate-spin mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Traitement…
                        </span>
                    </button>
                </div>
            </div>

        @else
            {{-- ── Branche 3 : paiement (panier 100% payable, ou post-confirmation split) ── --}}
            <div class="p-6 space-y-4">
                {{-- Si le mode quote_all a été confirmé, on bascule sur le bouton devis ── --}}
                @if ($splitConfirmed && $splitMode === 'quote_all')
                    <div class="p-4 text-sm text-amber-800 rounded-lg bg-amber-50 border border-amber-200">
                        Votre commande complète sera traitée en devis. Vous recevrez un lien de paiement
                        une fois les frais de port calculés.
                    </div>

                    @error('checkout')
                        <div class="p-4 text-sm text-red-700 rounded-lg bg-red-50">
                            {{ $message }}
                        </div>
                    @enderror

                    <form wire:submit="checkout">
                        <button class="px-5 py-3 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-500"
                                type="submit">
                            <span wire:loading.remove wire:target="checkout">Demander un devis</span>
                            <span wire:loading wire:target="checkout">
                                <svg class="w-5 h-5 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        </button>
                    </form>

                @else
                    {{-- Formulaire de paiement Stripe / SEPA ────────────────────── --}}
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

                    @error('checkout')
                        <div class="p-4 text-sm text-red-700 rounded-lg bg-red-50">
                            {{ $message }}
                        </div>
                    @enderror

                    @if ($paymentType === 'card')
                        <livewire:stripe.payment :cart="$cart"
                                                 :returnUrl="route('checkout.view', ['paymentType' => 'card'])" />
                    @elseif ($paymentType === 'sepa')
                        <livewire:sepa-payment-form :cart="$cart"
                                                     :returnUrl="route('checkout.view', ['paymentType' => 'sepa'])" />
                    @endif
                @endif
            </div>
        @endif
    @endif
</div>
