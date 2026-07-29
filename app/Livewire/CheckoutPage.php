<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Collection;
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
            $payment = Payments::driver($this->paymentType)->cart($this->cart)->withData([
                'payment_intent_client_secret' => $this->payment_intent_client_secret,
                'payment_intent' => $this->payment_intent,
            ])->authorize();

            if ($payment->success) {
                $this->redirectToOrderConfirmation($payment->orderId, confirmed: true);

                return;
            }

            // SEPA Direct Debit: Stripe returns 'processing' (async), not 'succeeded'.
            // The order was created by authorize() but placed_at is null until webhook confirms.
            // We place it manually here so the customer sees the recap page.
            if ($this->paymentType === 'sepa' && $payment->orderId) {
                Order::find($payment->orderId)?->update([
                    'placed_at' => now(),
                    'status' => 'payment-pending',
                ]);
                // Paiement SEPA encore en cours (processing) → pas de bannière verte.
                $this->redirectToOrderConfirmation($payment->orderId, confirmed: false);

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
     * Whether the current cart contains at least one product line with port mode 'quote'.
     *
     * A "quote" product cannot be paid immediately:
     * the operator must set the shipping cost and send a payment link.
     */
    public function getIsQuoteOnlyCartProperty(): bool
    {
        if (! $this->cart) {
            return false;
        }

        return $this->cart
            ->lines
            ->loadMissing('purchasable.product')
            ->contains(fn ($line) => ($line->purchasable?->product?->pko_port_mode ?? '') === 'quote');
    }

    public function checkout(): mixed
    {
        if ($this->isQuoteOnlyCart) {
            try {
                $order = $this->cart->createOrder();
            } catch (CartException $e) {
                $this->addError('checkout', $e->getMessage());

                return null;
            }

            $order->update(['placed_at' => now()]);

            // Commande sur devis : soumise (awaiting-quote), pas encore payée → pas de bannière verte.
            return $this->redirectToOrderConfirmation($order->id, confirmed: false);
        }

        $payment = Payments::driver($this->paymentType)->cart($this->cart)->withData([
            'payment_intent_client_secret' => $this->payment_intent_client_secret,
            'payment_intent' => $this->payment_intent,
        ])->authorize();

        if ($payment->success) {
            return $this->redirectToOrderConfirmation($payment->orderId, confirmed: true);
        }

        // Paiement non abouti : on ne redirige pas vers le récap de commande.
        return redirect()->route('checkout-success.view');
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
