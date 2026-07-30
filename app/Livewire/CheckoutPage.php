<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\CreateSplitQuoteOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Lunar\Exceptions\CartException;
use Lunar\Facades\CartSession;
use Lunar\Facades\Payments;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Address;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\Country;
use Lunar\Models\Order;

class CheckoutPage extends Component
{
    /**
     * The Cart instance.
     */
    public ?Cart $cart;

    /**
     * The shipping address form data.
     *
     * Plain array (not an Eloquent model): Livewire 3 forbids binding
     * wire:model to attributes of a model property.
     *
     * @var array<string, mixed>
     */
    public array $shipping = [];

    /**
     * The billing address form data.
     *
     * @var array<string, mixed>
     */
    public array $billing = [];

    /**
     * The current checkout step.
     */
    public int $currentStep = 1;

    /**
     * Whether the shipping address is the billing address too.
     */
    public bool $shippingIsBilling = true;

    /**
     * The chosen shipping option.
     */
    public $chosenShipping = null;

    /**
     * The checkout steps.
     */
    public array $steps = [
        'shipping_address' => 1,
        'shipping_option' => 2,
        'billing_address' => 3,
        'payment' => 4,
    ];

    /**
     * The payment type we want to use.
     */
    public string $paymentType = 'card';

    /**
     * How to handle a mixed cart (quote + payable lines).
     * 'split'     → pay payable now, quote order sent separately (default)
     * 'quote_all' → group everything into a single quote order
     */
    public string $splitMode = 'split';

    /**
     * True once the user has confirmed their split-mode choice.
     * Prevents the Stripe component from mounting before the cart is reduced.
     */
    public bool $splitConfirmed = false;

    /**
     * {@inheritDoc}
     */
    protected $listeners = [
        'cartUpdated' => 'refreshCart',
        'selectedShippingOption' => 'onShippingOptionSelected',
    ];

    public $payment_intent = null;

    public $payment_intent_client_secret = null;

    protected $queryString = [
        'payment_intent',
        'payment_intent_client_secret',
        'paymentType' => ['except' => 'card'],
    ];

    /**
     * {@inheritDoc}
     */
    public function rules(): array
    {
        return array_merge(
            $this->getAddressValidation('shipping'),
            $this->getAddressValidation('billing'),
            [
                'shippingIsBilling' => 'boolean',
            ]
        );
    }

    public function mount(): void
    {
        if (! $this->cart = CartSession::current()) {
            $this->redirect('/');

            return;
        }

        if ($this->payment_intent) {
            $result = $this->processPaymentAuthorize(
                $this->payment_intent_client_secret,
                $this->payment_intent,
            );

            if ($result !== null) {
                return;
            }
        }

        // Do we have a shipping address? Otherwise prefill from the customer profile.
        $this->shipping = $this->cart->shippingAddress
            ? $this->addressToArray($this->cart->shippingAddress)
            : $this->prefilledAddress();

        $this->billing = $this->cart->billingAddress
            ? $this->addressToArray($this->cart->billingAddress)
            : $this->prefilledAddress();

        $this->determineCheckoutStep();
    }

    /**
     * An empty address form, defaulted to the shop country.
     *
     * @return array<string, mixed>
     */
    protected function emptyAddress(): array
    {
        return [
            'first_name' => null,
            'last_name' => null,
            'company_name' => null,
            'line_one' => null,
            'line_two' => null,
            'line_three' => null,
            'city' => null,
            'state' => null,
            'postcode' => null,
            // Default to the shop country (single-country B2B shop).
            'country_id' => Country::orderBy('name')->value('id'),
            'contact_email' => null,
            'contact_phone' => null,
            'delivery_instructions' => null,
        ];
    }

    /**
     * Reduce a CartAddress to a plain form array.
     *
     * @return array<string, mixed>
     */
    protected function addressToArray(CartAddress $address): array
    {
        return array_merge(
            $this->emptyAddress(),
            $address->only(array_keys($this->emptyAddress())),
        );
    }

    /**
     * Build an address form pre-filled from the authenticated pro customer.
     *
     * @return array<string, mixed>
     */
    protected function prefilledAddress(): array
    {
        $address = $this->emptyAddress();

        if (! $customer = $this->cart->customer) {
            return $address;
        }

        $meta = $customer->meta;
        $user = $this->cart->user ?? auth()->user();

        return array_merge($address, array_filter([
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'company_name' => $customer->company_name,
            'contact_email' => $user?->email,
            'contact_phone' => data_get($meta, 'phone'),
            'line_one' => data_get($meta, 'sirene_address.line_1'),
            'city' => data_get($meta, 'sirene_address.city'),
            'postcode' => data_get($meta, 'sirene_address.postcode'),
        ], fn ($value) => filled($value)));
    }

    public function hydrate(): void
    {
        $this->cart = CartSession::current();
    }

    /**
     * Trigger an event to refresh addresses.
     */
    public function triggerAddressRefresh(): void
    {
        $this->dispatch('refreshAddress');
    }

    /**
     * Determines what checkout step we should be at.
     */
    public function determineCheckoutStep(): void
    {
        $shippingAddress = $this->cart->shippingAddress;
        $billingAddress = $this->cart->billingAddress;

        if ($shippingAddress) {
            if ($shippingAddress->id) {
                $this->currentStep = $this->steps['shipping_address'] + 1;
            }

            // Do we have a selected option?
            if ($this->shippingOption) {
                $this->chosenShipping = $this->shippingOption->getIdentifier();
                $this->currentStep = $this->steps['shipping_option'] + 1;
            } else {
                $this->currentStep = $this->steps['shipping_option'];

                return;
            }
        }

        if ($billingAddress) {
            $this->currentStep = $this->steps['billing_address'] + 1;
        }
    }

    /**
     * Refresh the cart instance.
     */
    public function refreshCart(): void
    {
        $this->cart = CartSession::current();
    }

    /**
     * Called when ShippingOptions component saves a selection.
     * Advances the checkout step after the option is persisted.
     */
    public function onShippingOptionSelected(): void
    {
        $this->refreshCart();
        $this->determineCheckoutStep();
    }

    /**
     * Return the shipping option.
     */
    public function getShippingOptionProperty()
    {
        $shippingAddress = $this->cart->shippingAddress;

        if (! $shippingAddress) {
            return;
        }

        if ($option = $shippingAddress->shipping_option) {
            return ShippingManifest::getOptions($this->cart)->first(function ($opt) use ($option) {
                return $opt->getIdentifier() == $option;
            });
        }

        return null;
    }

    /**
     * Save the address for a given type.
     */
    public function saveAddress(string $type): void
    {
        $this->validate(
            $this->getAddressValidation($type)
        );

        $address = (new CartAddress)->fill($this->{$type});

        if ($type == 'billing') {
            $this->cart->setBillingAddress($address);
            $this->billing = $this->addressToArray($this->cart->billingAddress);
        }

        if ($type == 'shipping') {
            $this->cart->setShippingAddress($address);
            $this->shipping = $this->addressToArray($this->cart->shippingAddress);

            if ($this->shippingIsBilling) {
                $this->cart->setBillingAddress((new CartAddress)->fill($this->shipping));
                $this->billing = $this->addressToArray($this->cart->billingAddress);
            }
        }

        $this->determineCheckoutStep();
    }

    /**
     * Whether ALL lines in the cart require a transport quote.
     * If only SOME lines are quote → isMixedCart.
     */
    public function getIsQuoteOnlyCartProperty(): bool
    {
        if (! $this->cart) {
            return false;
        }

        $lines = $this->cart->lines->loadMissing('purchasable.product');

        if ($lines->isEmpty()) {
            return false;
        }

        // True only when every product line is quote-mode (shipping lines are neutral)
        $productLines = $lines->filter(fn ($l) => $l->type === 'physical');

        return $productLines->isNotEmpty()
            && $productLines->every(fn ($l) => ($l->purchasable?->product?->pko_port_mode ?? '') === 'quote');
    }

    /**
     * Whether the cart contains both quote and non-quote lines.
     */
    public function getIsMixedCartProperty(): bool
    {
        if (! $this->cart) {
            return false;
        }

        $lines = $this->cart->lines->loadMissing('purchasable.product');
        $hasQuote = $lines->contains(fn ($l) => ($l->purchasable?->product?->pko_port_mode ?? '') === 'quote');
        $hasNonQuote = $lines->contains(fn ($l) => ($l->purchasable?->product?->pko_port_mode ?? '') !== 'quote');

        return $hasQuote && $hasNonQuote;
    }

    /**
     * Number of quote lines in the current cart.
     */
    public function getQuoteLineCountProperty(): int
    {
        if (! $this->cart) {
            return 0;
        }

        return $this->cart->lines
            ->loadMissing('purchasable.product')
            ->filter(fn ($l) => ($l->purchasable?->product?->pko_port_mode ?? '') === 'quote')
            ->count();
    }

    /**
     * Remove quote lines from the active cart and park them in cart.meta['split_pending'].
     * Must be called before the Stripe PaymentIntent is created (i.e. before the payment
     * partial renders the Stripe component).
     */
    public function applySplit(): void
    {
        if (! $this->cart || ! $this->isMixedCart) {
            return;
        }

        // Idempotence : split already applied
        $currentMeta = (array) ($this->cart->meta ?? []);
        if (! empty($currentMeta['split_pending'])) {
            return;
        }

        $quoteLines = $this->cart->lines
            ->loadMissing('purchasable.product')
            ->filter(fn ($l) => ($l->purchasable?->product?->pko_port_mode ?? '') === 'quote');

        $splitPending = $quoteLines->map(fn ($l) => [
            'purchasable_type' => $l->purchasable_type,
            'purchasable_id'   => $l->purchasable_id,
            'quantity'         => $l->quantity,
            'description'      => $l->purchasable->getDescription(),
            'identifier'       => $l->purchasable->getIdentifier(),
            'unit_price'       => $l->unitPrice?->value ?? 0,
            'unit_quantity'    => $l->purchasable->unit_quantity ?? 1,
            'sub_total'        => $l->subTotal?->value ?? 0,
            'tax_total'        => $l->taxAmount?->value ?? 0,
            'total'            => $l->total?->value ?? 0,
        ])->values()->toArray();

        // Persist split data in cart meta before removing lines
        $splitGroup = (string) Str::uuid();
        $this->cart->forceFill([
            'meta' => array_merge($currentMeta, [
                'split_pending' => $splitPending,
                'split_group'   => $splitGroup,
            ]),
        ])->save();

        // Remove quote lines from the active cart so Stripe sees only payable lines
        foreach ($quoteLines as $line) {
            CartSession::remove($line->id);
        }

        $this->cart = CartSession::current();

        // Re-validate the shipping option: franco threshold may have shifted after removing
        // quote lines, and an option valid for the full cart might no longer exist for the
        // reduced cart (e.g. weight bracket dropped, carrier changed).
        $currentOption = $this->cart?->shippingAddress?->shipping_option;
        $optionStillValid = $currentOption !== null && ShippingManifest::getOptions($this->cart)
            ->contains(fn ($opt) => $opt->getIdentifier() === $currentOption);

        if ($this->cart?->shippingAddress && (! $currentOption || ! $optionStillValid)) {
            $this->currentStep = $this->steps['shipping_option'];
        }

        $this->dispatch('cartUpdated');
    }

    /**
     * Confirm the split-mode choice and prepare the cart before Stripe mounts.
     * Called by the "Confirmer mon choix" button in the payment partial.
     */
    public function confirmSplitChoice(): void
    {
        if (! $this->isMixedCart) {
            $this->splitConfirmed = true;

            return;
        }

        // Guard: refuse split when a discount/coupon is active — allocation is undefined.
        $hasDiscount = ($this->cart->coupon_code !== null)
            || (($this->cart->discount_total?->value ?? 0) > 0);

        if ($hasDiscount && $this->splitMode === 'split') {
            $this->addError('splitMode', 'Un code promo est appliqué sur votre panier. Vous devez commander l\'intégralité en devis ou retirer le code promo avant de scinder la commande.');

            return;
        }

        if ($this->splitMode === 'split') {
            $this->applySplit();
        }

        $this->splitConfirmed = true;
    }

    public function checkout(): mixed
    {
        // 1. Panier 100% devis → flux devis direct (comportement actuel)
        if ($this->isQuoteOnlyCart) {
            return $this->checkoutQuoteOnly();
        }

        // 2. Panier mixte → choix "Tout en devis"
        if ($this->isMixedCart && $this->splitMode === 'quote_all') {
            return $this->checkoutQuoteOnly();
        }

        // 3. Flux paiement normal (panier payable uniquement, ou post-split)
        return $this->processPaymentAuthorize(
            $this->payment_intent_client_secret,
            $this->payment_intent,
        );
    }

    /**
     * Create a single awaiting-quote order from the current cart (no payment).
     */
    private function checkoutQuoteOnly(): mixed
    {
        try {
            $order = $this->cart->createOrder();
        } catch (CartException $e) {
            $this->addError('checkout', $e->getMessage());

            return null;
        }

        $order->update(['placed_at' => now()]);

        return $this->redirectToOrderConfirmation($order->id, confirmed: false);
    }

    /**
     * Run the Stripe/SEPA authorize flow and handle success/failure.
     * Called by both checkout() and mount() (3DS redirect return).
     */
    private function processPaymentAuthorize(?string $intentSecret, ?string $intentId): mixed
    {
        $payment = Payments::driver($this->paymentType)->cart($this->cart)->withData([
            'payment_intent_client_secret' => $intentSecret,
            'payment_intent'               => $intentId,
        ])->authorize();

        if ($payment->success) {
            $this->maybeCreateSplitQuoteOrder($payment->orderId);

            return $this->redirectToOrderConfirmation($payment->orderId, confirmed: true);
        }

        // SEPA Direct Debit : Stripe returns 'processing' (async), not 'succeeded'.
        if ($this->paymentType === 'sepa' && $payment->orderId) {
            Order::find($payment->orderId)?->update([
                'placed_at' => now(),
                'status'    => 'payment-pending',
            ]);
            $this->maybeCreateSplitQuoteOrder($payment->orderId);

            return $this->redirectToOrderConfirmation($payment->orderId, confirmed: false);
        }

        // Payment failed or was declined — return null so mount() re-renders the page
        // normally and the user sees the checkout form again (not a blank success page).
        $this->addError('checkout', __('Le paiement a été refusé ou annulé. Veuillez réessayer.'));

        return null;
    }

    /**
     * If the cart had split_pending lines, create the companion awaiting-quote order now.
     * Idempotent: check and creation are inside a single transaction with a pessimistic lock
     * on the payable order so that concurrent submissions cannot produce two quote orders.
     */
    private function maybeCreateSplitQuoteOrder(int $orderId): void
    {
        $cart = $this->cart->fresh();
        /** @var array<int, array<string, mixed>>|null $splitPending */
        $splitPending = $cart->meta['split_pending'] ?? null;

        if (empty($splitPending)) {
            return;
        }

        $splitGroup = $cart->meta['split_group'] ?? (string) Str::uuid();

        DB::transaction(function () use ($orderId, $splitPending, $splitGroup): void {
            $payableOrder = Order::lockForUpdate()->find($orderId);
            if (! $payableOrder) {
                return;
            }

            // Idempotence guard inside the lock: concurrent requests both pass the
            // exists() check above but only one can hold the row lock at a time.
            if (Order::where('meta->split_from', $orderId)->exists()) {
                return;
            }

            app(CreateSplitQuoteOrder::class)->execute($payableOrder, $splitPending, $splitGroup);
        });
    }

    /**
     * Vide le panier et renvoie le client vers le récap de sa commande.
     *
     * @param  bool  $confirmed  true = affiche la bannière verte « commande validée / paiement confirmé »
     */
    private function redirectToOrderConfirmation(?int $orderId, bool $confirmed): mixed
    {
        CartSession::forget();

        if (! $orderId) {
            return redirect()->route('account.orders');
        }

        if ($confirmed) {
            session()->flash('checkout_confirmed', true);
        }

        return redirect()->route('account.order.view', ['order' => $orderId]);
    }

    /**
     * Return the saved addresses of the authenticated customer.
     *
     * @return Collection<int, Address>
     */
    public function getCustomerAddressesProperty(): Collection
    {
        return $this->cart?->customer?->addresses()->get() ?? Collection::make();
    }

    /**
     * Apply a saved customer address to the form fields for the given type.
     */
    public function useCustomerAddress(int $addressId, string $type): void
    {
        $address = $this->customerAddresses->firstWhere('id', $addressId);

        if (! $address) {
            return;
        }

        $mapped = array_merge($this->emptyAddress(), [
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'company_name' => $address->company_name,
            'line_one' => $address->line_one,
            'line_two' => $address->line_two,
            'line_three' => $address->line_three,
            'city' => $address->city,
            'state' => $address->state,
            'postcode' => $address->postcode,
            'country_id' => $address->country_id,
            'contact_email' => $address->contact_email,
            'contact_phone' => $address->contact_phone,
        ]);

        $this->{$type} = array_filter($mapped, fn ($v) => $v !== null, ARRAY_FILTER_USE_VALUE) + $this->emptyAddress();
    }

    /**
     * Return the available countries.
     */
    public function getCountriesProperty(): Collection
    {
        return Country::orderBy('name')->get();
    }

    /**
     * Return the address validation rules for a given type.
     */
    protected function getAddressValidation(string $type): array
    {
        return [
            "{$type}.first_name" => 'required',
            "{$type}.last_name" => 'required',
            "{$type}.line_one" => 'required',
            "{$type}.country_id" => 'required',
            "{$type}.city" => 'required',
            "{$type}.postcode" => 'required',
            "{$type}.company_name" => 'nullable',
            "{$type}.line_two" => 'nullable',
            "{$type}.line_three" => 'nullable',
            "{$type}.state" => 'nullable',
            "{$type}.delivery_instructions" => 'nullable',
            "{$type}.contact_email" => 'required|email',
            "{$type}.contact_phone" => 'nullable',
        ];
    }

    public function render(): View
    {
        return view('livewire.checkout-page')
            ->layout('layouts.storefront');
    }
}
