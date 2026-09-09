<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\CartPage;
use App\Livewire\CheckoutPage;
use App\Livewire\Components\CouponCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\DiscountTypes\AmountOff;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

class CouponCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_le_panier_affiche_le_champ_code_promo(): void
    {
        $this->makeCartWithLine();

        Livewire::test(CartPage::class)
            ->assertSee('Code promo')
            ->assertSee('Appliquer');
    }

    public function test_un_panier_vide_n_affiche_pas_le_champ_code_promo(): void
    {
        Livewire::test(CartPage::class)
            ->assertDontSee('Code promo');
    }

    public function test_un_code_valide_applique_la_remise_et_baisse_le_total(): void
    {
        $cart = $this->makeCartWithLine();
        $this->makePercentCoupon('20OFF', 20);

        $before = $cart->calculate()->total->value;
        $this->assertGreaterThan(0, $before);

        Livewire::test(CouponCode::class)
            ->set('code', '20off')
            ->call('apply')
            ->assertHasNoErrors()
            ->assertSet('code', '')
            ->assertSee('20OFF');

        $cart = CartSession::current();
        $this->assertSame('20OFF', $cart->coupon_code);
        $this->assertGreaterThan(0, $cart->discountTotal->value);
        $this->assertLessThan($before, $cart->total->value);
    }

    public function test_un_code_invalide_est_refuse(): void
    {
        $this->makeCartWithLine();

        Livewire::test(CouponCode::class)
            ->set('code', 'INEXISTANT')
            ->call('apply')
            ->assertHasErrors('code');

        $this->assertNull(CartSession::current()->coupon_code);
        $this->assertSame(0, CartSession::current()->discountTotal?->value ?? 0);
    }

    public function test_un_code_hors_seuil_n_est_pas_conserve_sur_le_panier(): void
    {
        $cart = $this->makeCartWithLine();
        $currency = $cart->currency->code;

        Discount::create([
            'name' => 'Seuil inatteignable',
            'handle' => 'seuil-inatteignable',
            'coupon' => 'SEUIL',
            'type' => AmountOff::class,
            'data' => [
                'fixed_value' => false,
                'percentage' => 10,
                'min_prices' => [
                    $currency => 999_999_00,
                ],
            ],
            'starts_at' => now()->subMinute(),
        ]);

        Livewire::test(CouponCode::class)
            ->set('code', 'SEUIL')
            ->call('apply')
            ->assertHasErrors('code');

        $this->assertNull($cart->fresh()->coupon_code);
    }

    public function test_retirer_le_code_annule_la_remise(): void
    {
        $cart = $this->makeCartWithLine();
        $this->makePercentCoupon('20OFF', 20);

        Livewire::test(CouponCode::class)
            ->set('code', '20OFF')
            ->call('apply')
            ->assertHasNoErrors()
            ->call('remove')
            ->assertHasNoErrors()
            ->assertSee('Code promo');

        $cart = CartSession::current();
        $this->assertNull($cart->coupon_code);
        $this->assertSame(0, $cart->discountTotal?->value ?? 0);
    }

    public function test_le_recap_panier_affiche_la_ligne_de_remise(): void
    {
        $this->makeCartWithLine();
        $this->makePercentCoupon('20OFF', 20);

        Livewire::test(CouponCode::class)
            ->set('code', '20OFF')
            ->call('apply')
            ->assertHasNoErrors();

        Livewire::test(CartPage::class)
            ->assertSee('Remise')
            ->assertSee('20OFF');
    }

    public function test_le_checkout_affiche_le_champ_a_partir_de_l_etape_facturation(): void
    {
        $this->makeCartWithLine();

        Livewire::test(CheckoutPage::class)
            ->assertSet('currentStep', 1)
            ->assertDontSee('Code promo')
            ->set('shippingIsBilling', false)
            ->set('shipping.first_name', 'Romain')
            ->set('shipping.last_name', 'Galvez')
            ->set('shipping.contact_email', 'client@example.test')
            ->set('shipping.line_one', '54 Rue des Châtaigniers')
            ->set('shipping.city', 'Béziers')
            ->set('shipping.postcode', '34500')
            ->set('shipping.country_id', $this->franceId())
            ->call('saveAddress', 'shipping')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 2)
            ->assertSee('Code promo');
    }

    public function test_un_code_saisi_au_panier_reste_applique_au_checkout(): void
    {
        $this->makeCartWithLine();
        $this->makePercentCoupon('20OFF', 20);

        Livewire::test(CouponCode::class)
            ->set('code', '20OFF')
            ->call('apply')
            ->assertHasNoErrors();

        Livewire::test(CheckoutPage::class)
            ->set('shippingIsBilling', false)
            ->set('shipping.first_name', 'Romain')
            ->set('shipping.last_name', 'Galvez')
            ->set('shipping.contact_email', 'client@example.test')
            ->set('shipping.line_one', '54 Rue des Châtaigniers')
            ->set('shipping.city', 'Béziers')
            ->set('shipping.postcode', '34500')
            ->set('shipping.country_id', $this->franceId())
            ->call('saveAddress', 'shipping')
            ->assertHasNoErrors()
            ->assertSee('20OFF')
            ->assertSee('Remise');
    }

    private function makeCartWithLine(): Cart
    {
        $currency = Currency::query()->where('default', true)->firstOrFail();
        $channel = Channel::query()->where('default', true)->firstOrFail();
        $variant = ProductVariant::query()->first();
        $this->assertNotNull($variant, 'Le seeder doit fournir au moins une variante.');

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
            'channel_id' => $channel->id,
        ]);
        CartSession::use($cart);
        $cart->add($variant, 2);

        return CartSession::current();
    }

    private function makePercentCoupon(string $code, int $percentage): Discount
    {
        return Discount::create([
            'name' => $percentage.' % de remise',
            'handle' => str($code)->slug()->toString(),
            'coupon' => $code,
            'type' => AmountOff::class,
            'data' => [
                'fixed_value' => false,
                'percentage' => $percentage,
            ],
            'starts_at' => now()->subMinute(),
        ]);
    }

    private function franceId(): int
    {
        return (int) Country::query()->where('iso2', 'FR')->value('id');
    }
}
