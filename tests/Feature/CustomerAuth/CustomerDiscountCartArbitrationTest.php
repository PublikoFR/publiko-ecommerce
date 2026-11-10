<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Livewire\CartPage;
use App\Livewire\Components\CouponCode;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Lunar\DiscountTypes\AmountOff;
use Lunar\Facades\CartSession;
use Lunar\Facades\Discounts;
use Lunar\Models\Brand;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\CustomerAuth\Models\CustomerDiscount;
use Pko\CustomerAuth\Pricing\CartAdvantage;
use Pko\CustomerAuth\Pricing\CartDiscountArbiter;
use Pko\CustomerAuth\Pricing\CustomerDiscountResolver;
use Pko\Loyalty\DiscountTypes\LoyaltyVoucher;
use Tests\TestCase;

/**
 * Non-cumul remise client % ↔ promotions/coupons Lunar : meilleure des deux au
 * niveau du panier, sauf bon d'achat fidélité (toujours cumulé, déduit après).
 *
 * Panier type : 2 × 100,00 € HT, remise client sur la marque du produit.
 */
class CustomerDiscountCartArbitrationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->customer->users()->attach($user);
        $this->customer->customerGroups()->attach(CustomerGroup::getDefault());
        $this->brand = Brand::factory()->create();

        $this->actingAs($user);
        $this->makeCart($user);
    }

    public function test_stronger_promotion_replaces_customer_discount(): void
    {
        $this->customerDiscount('10');
        $this->promotion(20);

        $cart = $this->calculate();

        $this->assertSame(10000, $cart->lines->first()->unitPrice->value, 'prix non remisé');
        $this->assertSame(20000, $cart->subTotal->value);
        $this->assertSame(4000, $cart->discountTotal->value);
        $this->assertSame(16000, $cart->subTotalDiscounted->value);

        $decision = $this->decision($cart);
        $this->assertTrue($decision->promotionWins());
        $this->assertSame(2000, $decision->customerDiscountCents);
        $this->assertSame(4000, $decision->promotionCents);
    }

    public function test_stronger_customer_discount_discards_promotion(): void
    {
        $this->customerDiscount('30');
        $this->promotion(20);

        $cart = $this->calculate();

        $this->assertSame(7000, $cart->lines->first()->unitPrice->value);
        $this->assertSame(14000, $cart->subTotal->value);
        $this->assertSame(0, $cart->discountTotal->value);
        $this->assertTrue($cart->discounts->isEmpty(), 'la promotion ne doit pas être consommée');

        $decision = $this->decision($cart);
        $this->assertTrue($decision->customerDiscountWins());
        $this->assertSame(6000, $decision->customerDiscountCents);
        $this->assertSame(4000, $decision->promotionCents);
        $this->assertSame('Votre remise client est retenue', $decision->headline());
    }

    public function test_loyalty_voucher_stacks_after_customer_discount(): void
    {
        $this->customerDiscount('10');
        $this->voucher('FID-CUMUL', 5000);
        $this->useCoupon('FID-CUMUL');

        $cart = $this->calculate();

        $this->assertSame(18000, $cart->subTotal->value, 'remise client conservée');
        $this->assertSame(5000, $cart->discountTotal->value, 'bon déduit après la remise');
        $this->assertSame(13000, $cart->subTotalDiscounted->value);

        $decision = $this->decision($cart);
        $this->assertTrue($decision->customerDiscountWins());
        $this->assertSame(5000, $decision->stackedCents);
        $this->assertNull($decision->outrankedCoupon);
    }

    public function test_loyalty_voucher_stacks_with_winning_promotion(): void
    {
        $this->customerDiscount('10');
        $this->promotion(20);
        $this->voucher('FID-PROMO', 5000);
        $this->useCoupon('FID-PROMO');

        $cart = $this->calculate();

        // Promo : 200 - 40 = 160 < 180 (remise client) → promo, puis bon de 50.
        $this->assertSame(20000, $cart->subTotal->value);
        $this->assertSame(9000, $cart->discountTotal->value);
        $this->assertSame(11000, $cart->subTotalDiscounted->value);

        $decision = $this->decision($cart);
        $this->assertTrue($decision->promotionWins());
        $this->assertSame(5000, $decision->stackedCents);
    }

    public function test_coupon_beaten_by_customer_discount_is_refused_with_explicit_message(): void
    {
        $this->customerDiscount('30');
        $this->promotion(20, 'PROMO20');

        Livewire::test(CouponCode::class)
            ->set('code', 'promo20')
            ->call('apply')
            ->assertHasErrors('code')
            ->assertSee('ne se cumule pas avec votre remise client');

        $this->assertNull(CartSession::current()->coupon_code);
    }

    public function test_cart_page_shows_the_retained_advantage(): void
    {
        $this->customerDiscount('10');
        $this->promotion(20);

        // `add()` a calculé le panier avant la création des remises.
        $this->calculate();

        Livewire::test(CartPage::class)
            ->assertSee('Promotion retenue')
            ->assertSee('ne se cumulent pas');
    }

    public function test_customer_without_discount_keeps_standard_lunar_behaviour(): void
    {
        $this->promotion(20);

        $cart = $this->calculate();

        $this->assertSame(4000, $cart->discountTotal->value);
        $this->assertNull($this->decision($cart));
    }

    private function makeCart(User $user): void
    {
        $product = Product::factory()->create();
        DB::table('lunar_products')->where('id', $product->id)->update(['brand_id' => $this->brand->id]);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'purchasable' => 'always', 'stock' => 100]);
        $variant->prices()->create([
            'price' => 10000,
            'currency_id' => Currency::getDefault()->id,
            'min_quantity' => 1,
        ]);

        $cart = Cart::factory()->create([
            'currency_id' => Currency::getDefault()->id,
            'channel_id' => Channel::query()->where('default', true)->value('id'),
            'customer_id' => $this->customer->id,
            'user_id' => $user->id,
        ]);
        $cart->add($variant, 2);
        CartSession::use($cart);
    }

    private function customerDiscount(string $percent): void
    {
        CustomerDiscount::create([
            'customer_id' => $this->customer->id,
            'target_type' => CustomerDiscount::TARGET_BRAND,
            'target_id' => $this->brand->id,
            'percent' => $percent,
        ]);
        app(CustomerDiscountResolver::class)->flush();
    }

    private function promotion(int $percent, ?string $coupon = null): void
    {
        Discount::create([
            'name' => "Promo {$percent} %",
            'handle' => 'promo-'.$percent.'-'.uniqid(),
            'coupon' => $coupon,
            'type' => AmountOff::class,
            'data' => ['fixed_value' => false, 'percentage' => $percent],
            'starts_at' => now()->subMinute(),
        ]);
    }

    private function voucher(string $code, int $amountCents): void
    {
        Discount::create([
            'name' => "Bon d'achat fidélité test",
            'handle' => 'loyalty-voucher-test-'.uniqid(),
            'coupon' => $code,
            'type' => LoyaltyVoucher::class,
            'priority' => 0,
            'max_uses' => 1,
            'data' => [LoyaltyVoucher::DATA_AMOUNT => $amountCents],
            'starts_at' => now()->subMinute(),
        ]);
    }

    private function useCoupon(string $code): void
    {
        $cart = CartSession::current(false, false);
        $cart->coupon_code = $code;
        $cart->save();
    }

    private function calculate(): Cart
    {
        Discounts::resetDiscounts();

        return CartSession::current(false, false)->recalculate();
    }

    private function decision(Cart $cart): ?CartAdvantage
    {
        return app(CartDiscountArbiter::class)->decisionFor($cart);
    }
}
