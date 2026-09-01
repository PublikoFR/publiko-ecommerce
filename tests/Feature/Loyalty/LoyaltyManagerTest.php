<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Pko\Loyalty\Models\CustomerPoints;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\Loyalty\Models\PointsHistory;
use Pko\Loyalty\Models\Setting;
use Pko\Loyalty\Notifications\TierUnlockedAdmin;
use Pko\Loyalty\Services\LoyaltyManager;
use Tests\TestCase;

class LoyaltyManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config()->set('loyalty.admin_email', 'admin@weklo.test');
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
        Notification::fake();
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
        Notification::assertSentOnDemand(TierUnlockedAdmin::class);
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

    private function makePlacedOrder(Customer $customer, int $subTotalCents): Order
    {
        return Order::factory()->create([
            'customer_id' => $customer->id,
            'sub_total' => $subTotalCents,
            'placed_at' => now(),
        ]);
    }
}
