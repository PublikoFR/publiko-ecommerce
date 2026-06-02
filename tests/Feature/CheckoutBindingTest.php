<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CheckoutPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Tests\TestCase;

class CheckoutBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCart(): Cart
    {
        $currency = Currency::factory()->create(['default' => true]);
        Country::factory()->create(['name' => 'France', 'iso3' => 'FRA']);

        $cart = Cart::factory()->create(['currency_id' => $currency->id]);
        CartSession::use($cart);

        return $cart;
    }

    public function test_typed_values_pass_validation_and_persist(): void
    {
        $cart = $this->makeCart();

        Livewire::test(CheckoutPage::class)
            ->set('shippingIsBilling', true)
            ->set('shipping.first_name', 'Romain')
            ->set('shipping.last_name', 'GALVEZ')
            ->set('shipping.contact_email', 'riderfx3@gmail.com')
            ->set('shipping.line_one', '54 Rue des Châtaigniers')
            ->set('shipping.city', 'Béziers')
            ->set('shipping.postcode', '34500')
            ->set('shipping.country_id', Country::first()->id)
            ->call('saveAddress', 'shipping')
            ->assertHasNoErrors();

        $shipping = $cart->refresh()->shippingAddress;
        $this->assertNotNull($shipping, 'shipping address should persist');
        $this->assertSame('Romain', $shipping->first_name);
        $this->assertSame('Béziers', $shipping->city);

        // shippingIsBilling should mirror onto the billing address.
        $this->assertSame('Romain', $cart->billingAddress?->first_name);
    }

    public function test_country_defaults_to_shop_country_when_empty(): void
    {
        $this->makeCart();

        Livewire::test(CheckoutPage::class)
            ->assertSet('shipping.country_id', Country::first()->id);
    }
}
