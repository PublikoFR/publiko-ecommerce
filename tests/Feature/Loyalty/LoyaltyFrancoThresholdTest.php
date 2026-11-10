<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Lunar\Models\Cart;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Account\Livewire\LoyaltyPage;
use Pko\Loyalty\Enums\GiftStatus;
use Pko\Loyalty\Enums\GiftType;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\Loyalty\Services\LoyaltyFrancoThreshold;
use Pko\Loyalty\Services\LoyaltyManager;
use Pko\ShippingCommon\Contracts\CustomerFrancoThresholdProvider;
use Pko\ShippingCommon\Settings\ShippingSettings;
use Pko\StorefrontCms\Models\Setting as StorefrontSetting;
use Tests\TestCase;

/**
 * Palier fidélité « franco abaissé » : seuil de franco personnel.
 */
class LoyaltyFrancoThresholdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        StorefrontSetting::set('admin_email', 'admin@example.test');
        StorefrontSetting::set('shipping.franco.threshold_cents', 50000);
        LoyaltyTier::query()->update(['active' => false]);
    }

    private function francoTier(int $points, int $thresholdCents): LoyaltyTier
    {
        return LoyaltyTier::create([
            'name' => 'Franco '.$points,
            'points_required' => $points,
            'gift_type' => GiftType::Franco,
            'franco_threshold' => $thresholdCents,
            'active' => true,
        ]);
    }

    private function threshold(): LoyaltyFrancoThreshold
    {
        return app(LoyaltyFrancoThreshold::class);
    }

    public function test_provider_est_lie_au_contrat_shipping(): void
    {
        $this->assertInstanceOf(LoyaltyFrancoThreshold::class, app(CustomerFrancoThresholdProvider::class));
    }

    public function test_deblocage_donne_un_seuil_personnel_et_le_plus_bas_gagne(): void
    {
        $customer = Customer::factory()->create();
        $this->francoTier(50, 30000);
        $this->francoTier(100, 25000);
        LoyaltyTier::create(['name' => 'Bon', 'points_required' => 20, 'gift_type' => GiftType::Voucher, 'voucher_amount' => 1000, 'active' => true]);

        $this->assertNull($this->threshold()->forCustomer($customer->id));

        $this->makePlacedOrder($customer, 60_00);
        $this->assertSame(30000, $this->threshold()->forCustomer($customer->id));

        $this->makePlacedOrder($customer, 50_00);
        $this->assertSame(25000, $this->threshold()->forCustomer($customer->id));
    }

    public function test_seuil_effectif_du_panier_via_customer_ou_user(): void
    {
        $customer = Customer::factory()->create();
        $this->francoTier(50, 25000);
        $this->makePlacedOrder($customer, 60_00);

        $this->assertSame(25000, ShippingSettings::thresholdCentsForCart(new Cart(['customer_id' => $customer->id])));

        $user = User::factory()->create();
        $customer->users()->attach($user->id);
        $this->assertSame(25000, ShippingSettings::thresholdCentsForCart(new Cart(['user_id' => $user->id])));

        $this->assertSame(50000, ShippingSettings::thresholdCentsForCart(new Cart));
        $this->assertSame(50000, ShippingSettings::thresholdCentsForCart(new Cart(['customer_id' => Customer::factory()->create()->id])));
    }

    public function test_avantage_expire_au_31_decembre(): void
    {
        $customer = Customer::factory()->create();
        $this->francoTier(50, 25000);

        $this->travelTo(now()->setDate(2025, 12, 31)->setTime(12, 0));
        $this->makePlacedOrder($customer, 60_00);
        $this->assertSame(25000, $this->threshold()->forCustomer($customer->id));

        $this->travelTo(now()->setDate(2026, 1, 1)->setTime(0, 1));
        $this->assertNull($this->threshold()->forCustomer($customer->id));
    }

    public function test_remboursement_qui_fait_perdre_le_palier_revoque_le_franco(): void
    {
        $customer = Customer::factory()->create();
        $tier = $this->francoTier(100, 25000);

        $order = $this->makePlacedOrder($customer, 120_00, totalCents: 144_00);
        // Même un déblocage déjà traité (« envoyé ») tombe avec le palier.
        GiftHistory::where('tier_id', $tier->id)->update(['status' => GiftStatus::Sent->value]);
        $this->assertSame(25000, $this->threshold()->forCustomer($customer->id));

        $this->makeRefund($order, 72_00); // moitié → 60 points < 100

        $this->assertDatabaseMissing('pko_loyalty_gift_history', ['customer_id' => $customer->id, 'tier_id' => $tier->id]);
        $this->assertNull($this->threshold()->forCustomer($customer->id));
    }

    public function test_snapshot_expose_le_seuil_personnel(): void
    {
        $customer = Customer::factory()->create();
        $this->francoTier(50, 25000);
        $this->makePlacedOrder($customer, 60_00);

        $snapshot = app(LoyaltyManager::class)->getCustomerSnapshot($customer->id);

        $this->assertSame(25000, $snapshot['franco_threshold']);
    }

    public function test_espace_fidelite_affiche_l_avantage_franco(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create();
        $customer->users()->attach($user->id);
        $this->francoTier(50, 25000);
        $this->makePlacedOrder($customer, 60_00);

        $this->actingAs($user);

        Livewire::test(LoyaltyPage::class)
            ->assertSee('Livraison standard offerte dès')
            ->assertSee('250')
            ->assertSee('31/12/'.now()->year)
            ->assertSee('Actif');
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
