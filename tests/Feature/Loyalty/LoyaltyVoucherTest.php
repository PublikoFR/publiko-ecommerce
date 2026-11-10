<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Lunar\Facades\Discounts;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Lunar\Models\Transaction;
use Pko\Loyalty\DiscountTypes\LoyaltyVoucher;
use Pko\Loyalty\Enums\GiftStatus;
use Pko\Loyalty\Enums\GiftType;
use Pko\Loyalty\Mail\LoyaltyVoucherMail;
use Pko\Loyalty\Mail\TierUnlockedMail;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\Loyalty\Services\LoyaltyManager;
use Pko\Loyalty\Services\LoyaltyVoucherService;
use Pko\StorefrontCms\Models\Setting as StorefrontSetting;
use Tests\TestCase;

/**
 * Palier « bon d'achat » : un coupon Lunar à usage unique est émis au déblocage.
 */
class LoyaltyVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        StorefrontSetting::set('admin_email', 'admin@example.test');
        LoyaltyTier::query()->update(['active' => false]); // neutralise les paliers de démo
    }

    public function test_unlocking_a_voucher_tier_issues_a_single_use_customer_coupon(): void
    {
        Mail::fake();
        $customer = $this->makeCustomerWithUser();
        $tier = $this->voucherTier(50, 50_00);

        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(10, 0));
        $this->makePlacedOrder($customer, 100_00);

        $history = GiftHistory::where('customer_id', $customer->id)->where('tier_id', $tier->id)->firstOrFail();
        $discount = $history->discount;

        $this->assertNotNull($discount);
        $this->assertSame(LoyaltyVoucher::class, $discount->type);
        $this->assertTrue(LoyaltyVoucher::isLoyaltyVoucher($discount));
        $this->assertStringStartsWith(LoyaltyVoucherService::CODE_PREFIX, (string) $discount->coupon);
        $this->assertSame(1, (int) $discount->max_uses);
        $this->assertSame(50_00, LoyaltyVoucherService::amount($discount));
        $this->assertSame('2026-05-09', $discount->ends_at->toDateString()); // 60 jours
        $this->assertSame([$customer->id], $discount->customers()->pluck('lunar_customers.id')->all());
        $this->assertSame(LoyaltyVoucherService::STATUS_ACTIVE, $history->voucher()['status']);

        Mail::assertQueued(LoyaltyVoucherMail::class, function (LoyaltyVoucherMail $mail) use ($discount): bool {
            return $mail->values['voucher_code'] === $discount->coupon
                && $mail->values['voucher_amount'] === '50 €'
                && $mail->values['voucher_expires_at'] === '09/05/2026';
        });
        // Un seul e-mail client : le bon remplace l'e-mail générique de palier.
        Mail::assertNotSent(TierUnlockedMail::class);
        Mail::assertNotQueued(TierUnlockedMail::class);
        $this->assertTrue((bool) $history->email_sent);
    }

    public function test_voucher_is_issued_once_per_gift_history(): void
    {
        Mail::fake();
        $customer = $this->makeCustomerWithUser();
        $this->voucherTier(50, 50_00);
        $this->makePlacedOrder($customer, 100_00);

        $history = GiftHistory::where('customer_id', $customer->id)->firstOrFail();
        $first = $history->discount_id;

        $again = app(LoyaltyVoucherService::class)->issueFor($history->fresh());
        app(LoyaltyManager::class)->awardForOrder($this->makePlacedOrder($customer, 10_00));

        $this->assertSame($first, $again?->id);
        $this->assertSame(1, Discount::where('type', LoyaltyVoucher::class)->count());
    }

    public function test_non_voucher_tiers_and_voucher_without_amount_issue_nothing(): void
    {
        Mail::fake();
        $customer = $this->makeCustomerWithUser();
        LoyaltyTier::create(['name' => 'Franco', 'points_required' => 10, 'gift_type' => GiftType::Franco, 'franco_threshold' => 300_00, 'active' => true]);
        LoyaltyTier::create(['name' => 'BFA', 'points_required' => 20, 'gift_type' => GiftType::Bfa, 'bfa_percent' => '2.00', 'active' => true]);
        LoyaltyTier::create(['name' => 'Vide', 'points_required' => 30, 'gift_type' => GiftType::Voucher, 'active' => true]);

        $this->makePlacedOrder($customer, 100_00);

        $this->assertSame(3, GiftHistory::where('customer_id', $customer->id)->count());
        $this->assertSame(0, Discount::where('type', LoyaltyVoucher::class)->count());
        Mail::assertSent(TierUnlockedMail::class, 3);
    }

    public function test_no_retroactive_voucher_for_gifts_unlocked_before(): void
    {
        Mail::fake();
        $customer = $this->makeCustomerWithUser();
        $tier = $this->voucherTier(50, 50_00);

        // Déblocage antérieur à la fonctionnalité : pas de bon lié.
        GiftHistory::create([
            'customer_id' => $customer->id,
            'tier_id' => $tier->id,
            'year' => (int) now()->year,
            'points_at_unlock' => 60,
            'status' => GiftStatus::Pending,
            'unlocked_at' => now()->subMonth(),
        ]);

        $this->makePlacedOrder($customer, 100_00);
        $this->artisan('loyalty:recalculate')->assertSuccessful();

        $this->assertSame(1, GiftHistory::where('customer_id', $customer->id)->count());
        $this->assertNull(GiftHistory::where('customer_id', $customer->id)->value('discount_id'));
        Mail::assertNotQueued(LoyaltyVoucherMail::class, fn (LoyaltyVoucherMail $m): bool => $m->customer?->id === $customer->id);
    }

    public function test_refund_losing_the_tier_disables_an_unused_voucher(): void
    {
        Mail::fake();
        $customer = $this->makeCustomerWithUser();
        $tier = $this->voucherTier(50, 50_00);
        $order = $this->makePlacedOrder($customer, 100_00, totalCents: 120_00);
        $discount = GiftHistory::where('tier_id', $tier->id)->firstOrFail()->discount;
        $code = (string) $discount->coupon;
        $this->assertTrue(Discounts::validateCoupon($code));

        $this->makeRefund($order, 120_00);

        $discount->refresh();
        $this->assertSame(LoyaltyVoucherService::STATUS_DISABLED, LoyaltyVoucherService::status($discount));
        $this->assertFalse(Discounts::validateCoupon($code));
        $this->assertDatabaseMissing('pko_loyalty_gift_history', ['customer_id' => $customer->id, 'tier_id' => $tier->id]);
    }

    public function test_refund_keeps_an_already_used_voucher(): void
    {
        Mail::fake();
        $customer = $this->makeCustomerWithUser();
        $tier = $this->voucherTier(50, 50_00);
        $order = $this->makePlacedOrder($customer, 100_00, totalCents: 120_00);
        $history = GiftHistory::where('tier_id', $tier->id)->firstOrFail();
        $history->discount->update(['uses' => 1]);

        $this->makeRefund($order, 120_00);

        $this->assertDatabaseHas('pko_loyalty_gift_history', ['id' => $history->id, 'discount_id' => $history->discount_id]);
        $this->assertSame(LoyaltyVoucherService::STATUS_USED, LoyaltyVoucherService::status($history->discount->fresh()));
    }

    public function test_voucher_applies_on_the_customer_cart_capped_to_its_subtotal(): void
    {
        Mail::fake();
        $customer = $this->makeCustomerWithUser();
        $this->voucherTier(50, 999_999_00); // plus gros que n'importe quel panier
        $this->makePlacedOrder($customer, 100_00);
        $code = (string) Discount::where('type', LoyaltyVoucher::class)->value('coupon');

        $cart = $this->makeCart($customer);
        $cart->coupon_code = $code;
        $cart->save();
        Discounts::resetDiscounts();
        $cart = $cart->calculate();

        // Usage unique : le bon est plafonné au panier HT, le reste est perdu.
        $this->assertGreaterThan(0, $cart->subTotal->value);
        $this->assertSame($cart->subTotal->value, $cart->discountTotal->value);
    }

    public function test_voucher_is_reserved_to_its_customer(): void
    {
        Mail::fake();
        $owner = $this->makeCustomerWithUser();
        $this->voucherTier(50, 1_00);
        $this->makePlacedOrder($owner, 100_00);
        $code = (string) Discount::where('type', LoyaltyVoucher::class)->value('coupon');

        $ownerCart = $this->makeCart($owner);
        $ownerCart->update(['coupon_code' => $code]);
        Discounts::resetDiscounts();
        $this->assertSame(1_00, $ownerCart->calculate()->discountTotal->value);

        $otherCart = $this->makeCart(Customer::factory()->create());
        $otherCart->update(['coupon_code' => $code]);
        Discounts::resetDiscounts();
        $this->assertSame(0, $otherCart->calculate()->discountTotal?->value ?? 0);
    }

    public function test_snapshot_exposes_the_voucher_for_the_account_page(): void
    {
        Mail::fake();
        $customer = $this->makeCustomerWithUser();
        $this->voucherTier(50, 50_00);
        $this->makePlacedOrder($customer, 100_00);

        $gift = app(LoyaltyManager::class)->getCustomerSnapshot($customer->id)['gift_history']->first();
        $voucher = $gift->voucher();

        $this->assertNotNull($voucher);
        $this->assertSame(50_00, $voucher['amount']);
        $this->assertSame('Valable', $voucher['status_label']);
        $this->assertSame("Bon d'achat de 50 € HT", $gift->tier->giftLabel());
    }

    private function voucherTier(int $points, int $amountCents): LoyaltyTier
    {
        return LoyaltyTier::create([
            'name' => 'Bronze',
            'points_required' => $points,
            'gift_type' => GiftType::Voucher,
            'voucher_amount' => $amountCents,
            'active' => true,
        ]);
    }

    private function makeCustomerWithUser(): Customer
    {
        $customer = Customer::factory()->create(['first_name' => 'Camille']);
        $customer->users()->attach(User::factory()->create(['email' => 'client-'.uniqid().'@example.test']));

        return $customer;
    }

    private function makeCart(Customer $customer): Cart
    {
        $variant = ProductVariant::query()->first();
        $this->assertNotNull($variant, 'Le seeder doit fournir au moins une variante.');

        $cart = Cart::factory()->create([
            'currency_id' => Currency::query()->where('default', true)->value('id'),
            'channel_id' => Channel::query()->where('default', true)->value('id'),
            'customer_id' => $customer->id,
        ]);
        $cart->add($variant, 2);

        return $cart->fresh();
    }

    private function makeRefund(Order $order, int $amountCents): Transaction
    {
        return Transaction::create([
            'order_id' => $order->id,
            'success' => true,
            'type' => 'refund',
            'driver' => 'offline',
            'amount' => $amountCents,
            'reference' => 'test-'.uniqid(),
            'status' => 'refunded',
            'card_type' => 'offline',
            'last_four' => '0000',
        ]);
    }

    private function makePlacedOrder(Customer $customer, int $subTotalCents, ?int $totalCents = null): Order
    {
        return Order::factory()->create([
            'customer_id' => $customer->id,
            'sub_total' => $subTotalCents,
            'total' => $totalCents ?? $subTotalCents,
            'placed_at' => now(),
        ]);
    }
}
