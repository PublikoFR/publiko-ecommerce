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
        config()->set('loyalty.admin_email', 'admin@mde.test');
    }

    public function test_points_ratio_falls_back_when_zero(): void
    {
        Setting::set('points_ratio', '0');
        $this->assertSame(1.0, app(LoyaltyManager::class)->pointsRatio());
    }

    public function test_award_creates_points_history_and_customer_points(): void
    {
        Notification::fake();
        $customer = Customer::first() ?? Customer::factory()->create();
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
        $customer = Customer::first();
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
        $customer = Customer::first();
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
        $customer = Customer::first();
        LoyaltyTier::create(['name' => 'A', 'points_required' => 10, 'gift_title' => 'X', 'active' => true]);

        $manager = app(LoyaltyManager::class);
        $manager->awardForOrder($this->makePlacedOrder($customer, 50_00));
        $manager->awardForOrder($this->makePlacedOrder($customer, 50_00));

        $this->assertSame(1, GiftHistory::where('customer_id', $customer->id)->count());
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
