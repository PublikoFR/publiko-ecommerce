<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Lunar\Facades\CartSession;
use Lunar\Facades\Payments;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\Country;

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
    public string $paymentType = 'cash-in-hand';

    /**
     * {@inheritDoc}
     */
    protected $listeners = [
        'cartUpdated' => 'refreshCart',
        'selectedShippingOption' => 'refreshCart',
    ];

    public $payment_intent = null;

    public $payment_intent_client_secret = null;

    protected $queryString = [
        'payment_intent',
        'payment_intent_client_secret',
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
                'chosenShipping' => 'required',
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
                redirect()->route('checkout-success.view');

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
                $this->chosenShipping = $this->shippingOptions->first()?->getIdentifier();

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
     * Save the selected shipping option.
     */
    public function saveShippingOption(): void
    {
        $this->validate(['chosenShipping' => 'required']);

        $option = $this->shippingOptions->first(fn ($option) => $option->getIdentifier() == $this->chosenShipping);

        if (! $option) {
            $this->addError('chosenShipping', __('Please select an available shipping option.'));

            return;
        }

        CartSession::setShippingOption($option);

        $this->refreshCart();

        $this->determineCheckoutStep();
    }

    public function checkout()
    {
        $payment = Payments::cart($this->cart)->withData([
            'payment_intent_client_secret' => $this->payment_intent_client_secret,
            'payment_intent' => $this->payment_intent,
        ])->authorize();

        if ($payment->success) {
            redirect()->route('checkout-success.view');

            return;
        }

        return redirect()->route('checkout-success.view');
    }

    /**
     * Return the available countries.
     */
    public function getCountriesProperty(): Collection
    {
        return Country::orderBy('name')->get();
    }

    /**
     * Return available shipping options.
     */
    public function getShippingOptionsProperty(): Collection
    {
        return ShippingManifest::getOptions(
            $this->cart
        );
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
