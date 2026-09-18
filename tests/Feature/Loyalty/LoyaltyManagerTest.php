<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Loyalty\Enums\GiftStatus;
use Pko\Loyalty\Mail\TierUnlockedAdminMail;
use Pko\Loyalty\Models\CustomerPoints;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\Loyalty\Models\PointsHistory;
use Pko\Loyalty\Models\Setting;
use Pko\Loyalty\Services\LoyaltyManager;
use Pko\StorefrontCms\Models\Setting as StorefrontSetting;
use Tests\TestCase;

class LoyaltyManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        StorefrontSetting::set('admin_email', 'admin@weklo.test');
    }

    public function test_points_ratio_falls_back_when_zero(): void
    {
        Setting::set('points_ratio', '0');
        $this->assertSame(1.0, app(LoyaltyManager::class)->pointsRatio());
    }

    public function test_award_creates_points_history_and_customer_points(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create();
        $order = $this->makePlacedOrder($customer, subTotalCents: 100_00); // 100€HT

        app(LoyaltyManager::class)->awardForOrder($order);

        $this->assertDatabaseHas('pko_loyalty_points_history', [
            'order_id' => $order->id,
            'points_earned' => 100,
        ]);
        $this->assertDatabaseHas('pko_loyalty_customer_points', [
            'customer_id' => $customer->id,
            'total_points' => 100,
        ]);
    }

    public function test_award_is_idempotent_per_order(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create();
        $order = $this->makePlacedOrder($customer, 100_00);
        $manager = app(LoyaltyManager::class);

        $manager->awardForOrder($order);
        $manager->awardForOrder($order);

        $this->assertSame(1, PointsHistory::where('order_id', $order->id)->count());
        $this->assertSame(100, (int) CustomerPoints::where('customer_id', $customer->id)->value('total_points'));
    }

    public function test_unlocks_tier_and_dispatches_notifications(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $tier = LoyaltyTier::create([
            'name' => 'Bronze',
            'points_required' => 50,
            'gift_title' => 'Cadeau',
            'active' => true,
            'position' => 1,
        ]);
        $order = $this->makePlacedOrder($customer, 100_00);

        app(LoyaltyManager::class)->awardForOrder($order);

        $this->assertDatabaseHas('pko_loyalty_gift_history', [
            'customer_id' => $customer->id,
            'tier_id' => $tier->id,
        ]);
        Mail::assertSent(TierUnlockedAdminMail::class, function (TierUnlockedAdminMail $mail): bool {
            return $mail->hasTo('admin@weklo.test')
                && $mail->values['tier_name'] === 'Bronze';
        });
    }

    public function test_no_double_unlock_for_same_tier(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create();
        LoyaltyTier::create(['name' => 'A', 'points_required' => 10, 'gift_title' => 'X', 'active' => true]);

        $manager = app(LoyaltyManager::class);
        $manager->awardForOrder($this->makePlacedOrder($customer, 50_00));
        $manager->awardForOrder($this->makePlacedOrder($customer, 50_00));

        $this->assertSame(1, GiftHistory::where('customer_id', $customer->id)->count());
    }

    public function test_unlocks_all_skipped_tiers_in_a_single_order(): void
    {
        Notification::fake();
        LoyaltyTier::query()->update(['active' => false]); // neutralise les paliers seedés par défaut
        $customer = Customer::factory()->create();
        $bronze = LoyaltyTier::create(['name' => 'Bronze', 'points_required' => 50, 'gift_title' => 'X', 'active' => true]);
        $argent = LoyaltyTier::create(['name' => 'Argent', 'points_required' => 1000, 'gift_title' => 'Y', 'active' => true]);
        $or = LoyaltyTier::create(['name' => 'Or', 'points_required' => 2000, 'gift_title' => 'Z', 'active' => true]);

        // Une seule commande fait franchir les 3 paliers d'un coup.
        app(LoyaltyManager::class)->awardForOrder($this->makePlacedOrder($customer, 300_000_00));

        $this->assertSame(3, GiftHistory::where('customer_id', $customer->id)->count());
        foreach ([$bronze, $argent, $or] as $tier) {
            $this->assertDatabaseHas('pko_loyalty_gift_history', [
                'customer_id' => $customer->id,
                'tier_id' => $tier->id,
            ]);
        }
        $this->assertSame(
            $or->id,
            (int) CustomerPoints::where('customer_id', $customer->id)->value('current_tier_id')
        );
    }

    public function test_recalculate_backfills_tier_added_after_customer_already_passed_it(): void
    {
        Notification::fake();
        LoyaltyTier::query()->update(['active' => false]); // neutralise les paliers seedés par défaut
        $customer = Customer::factory()->create();
        $argent = LoyaltyTier::create(['name' => 'Argent', 'points_required' => 1000, 'gift_title' => 'TV', 'active' => true]);

        $manager = app(LoyaltyManager::class);
        $manager->awardForOrder($this->makePlacedOrder($customer, 182_300_00));

        // Palier ajouté après coup, sous le solde déjà acquis : jamais débloqué tant
        // qu'aucune commande ne redéclenche le calcul.
        $bronze = LoyaltyTier::create(['name' => 'Bronze', 'points_required' => 50, 'gift_title' => "Bon d'achat 50€", 'active' => true]);

        $this->assertDatabaseMissing('pko_loyalty_gift_history', [
            'customer_id' => $customer->id,
            'tier_id' => $bronze->id,
        ]);

        $cp = CustomerPoints::where('customer_id', $customer->id)->first();
        $unlockedCount = $manager->recalculateForCustomer($cp);

        $this->assertSame(1, $unlockedCount);
        $this->assertDatabaseHas('pko_loyalty_gift_history', [
            'customer_id' => $customer->id,
            'tier_id' => $bronze->id,
        ]);
        // Le palier déjà débloqué (Argent) ne doit pas être dupliqué.
        $this->assertSame(2, GiftHistory::where('customer_id', $customer->id)->count());
        $this->assertDatabaseHas('pko_loyalty_gift_history', [
            'customer_id' => $customer->id,
            'tier_id' => $argent->id,
        ]);
    }

    public function test_balance_from_previous_year_is_reset_on_next_award(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create();

        $this->travelTo(now()->setDate(2025, 12, 20));
        $this->makePlacedOrder($customer, 100_00);
        $this->assertSame(100, (int) CustomerPoints::where('customer_id', $customer->id)->value('total_points'));

        $this->travelTo(now()->setDate(2026, 1, 3));
        $this->makePlacedOrder($customer, 30_00);

        $cp = CustomerPoints::where('customer_id', $customer->id)->first();
        $this->assertSame(30, (int) $cp->total_points);
        $this->assertSame(2026, (int) $cp->points_year);
    }

    public function test_reset_command_zeroes_past_year_balances_only(): void
    {
        Notification::fake();
        $old = Customer::factory()->create();
        $fresh = Customer::factory()->create();

        $this->travelTo(now()->setDate(2025, 11, 2));
        $this->makePlacedOrder($old, 80_00);
        $this->travelTo(now()->setDate(2026, 1, 1));
        $this->makePlacedOrder($fresh, 40_00);

        $this->artisan('loyalty:reset-annual')->assertSuccessful();

        $this->assertSame(0, (int) CustomerPoints::where('customer_id', $old->id)->value('total_points'));
        $this->assertNull(CustomerPoints::where('customer_id', $old->id)->value('current_tier_id'));
        $this->assertSame(40, (int) CustomerPoints::where('customer_id', $fresh->id)->value('total_points'));
        $this->assertSame(0, app(LoyaltyManager::class)->getCustomerSnapshot($old->id)['total_points']);
    }

    public function test_tier_can_be_unlocked_again_the_next_year(): void
    {
        Notification::fake();
        LoyaltyTier::query()->update(['active' => false]);
        $customer = Customer::factory()->create();
        $tier = LoyaltyTier::create(['name' => 'Bronze', 'points_required' => 50, 'gift_title' => 'X', 'active' => true]);

        $this->travelTo(now()->setDate(2025, 6, 1));
        $this->makePlacedOrder($customer, 60_00);
        $this->travelTo(now()->setDate(2026, 2, 1));
        $this->makePlacedOrder($customer, 60_00);

        $this->assertSame(2, GiftHistory::where('customer_id', $customer->id)->where('tier_id', $tier->id)->count());
        $this->assertEqualsCanonicalizing(
            [2025, 2026],
            GiftHistory::where('customer_id', $customer->id)->pluck('year')->all(),
        );
    }

    public function test_partial_refunds_revoke_points_prorata_and_idempotently(): void
    {
        Notification::fake();
        LoyaltyTier::query()->update(['active' => false]);
        $customer = Customer::factory()->create();
        $order = $this->makePlacedOrder($customer, 100_00, totalCents: 120_00);

        $this->makeRefund($order, 30_00); // 25 % du TTC
        $this->assertSame(75, (int) CustomerPoints::where('customer_id', $customer->id)->value('total_points'));

        // Rejouer ne retire rien de plus.
        app(LoyaltyManager::class)->revokeForRefunds($order);
        $this->assertSame(75, (int) CustomerPoints::where('customer_id', $customer->id)->value('total_points'));

        // Le solde du remboursement retire le reste, jamais plus que gagné.
        $this->makeRefund($order, 90_00);
        $this->assertSame(0, (int) CustomerPoints::where('customer_id', $customer->id)->value('total_points'));
        $this->assertSame(100, (int) PointsHistory::where('order_id', $order->id)->value('points_revoked'));
    }

    public function test_failed_or_non_refund_transactions_do_not_revoke(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create();
        $order = $this->makePlacedOrder($customer, 100_00, totalCents: 120_00);

        $this->makeRefund($order, 120_00, success: false);
        $this->makeRefund($order, 120_00, type: 'capture');

        $this->assertSame(100, (int) CustomerPoints::where('customer_id', $customer->id)->value('total_points'));
    }

    public function test_refund_cancels_pending_gift_but_keeps_processed_one(): void
    {
        Notification::fake();
        LoyaltyTier::query()->update(['active' => false]);
        $customer = Customer::factory()->create();
        $bronze = LoyaltyTier::create(['name' => 'Bronze', 'points_required' => 50, 'gift_title' => 'X', 'active' => true]);
        $argent = LoyaltyTier::create(['name' => 'Argent', 'points_required' => 150, 'gift_title' => 'Y', 'active' => true]);

        $this->makePlacedOrder($customer, 60_00, totalCents: 72_00);
        GiftHistory::where('tier_id', $bronze->id)->update(['status' => GiftStatus::Sent->value]);
        $order = $this->makePlacedOrder($customer, 100_00, totalCents: 120_00);
        $this->assertSame(2, GiftHistory::where('customer_id', $customer->id)->count());

        $this->makeRefund($order, 120_00);

        $cp = CustomerPoints::where('customer_id', $customer->id)->first();
        $this->assertSame(60, (int) $cp->total_points);
        $this->assertSame($bronze->id, (int) $cp->current_tier_id);
        $this->assertDatabaseMissing('pko_loyalty_gift_history', ['customer_id' => $customer->id, 'tier_id' => $argent->id]);
        $this->assertDatabaseHas('pko_loyalty_gift_history', ['customer_id' => $customer->id, 'tier_id' => $bronze->id]);
    }

    public function test_refund_of_previous_year_order_is_ignored(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create();

        $this->travelTo(now()->setDate(2025, 12, 28));
        $order = $this->makePlacedOrder($customer, 100_00, totalCents: 120_00);
        $this->travelTo(now()->setDate(2026, 1, 10));
        $this->makePlacedOrder($customer, 40_00);

        $this->makeRefund($order, 120_00);

        $this->assertSame(40, (int) CustomerPoints::where('customer_id', $customer->id)->value('total_points'));
        $this->assertSame(0, (int) PointsHistory::where('order_id', $order->id)->value('points_revoked'));
    }

    private function makeRefund(Order $order, int $amountCents, bool $success = true, string $type = 'refund'): Transaction
    {
        return Transaction::create([
            'order_id' => $order->id,
            'success' => $success,
            'type' => $type,
            'driver' => 'offline',
            'amount' => $amountCents,
            'reference' => 'test-'.uniqid(),
            'status' => $success ? 'refunded' : 'failed',
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
