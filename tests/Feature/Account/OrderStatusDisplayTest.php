<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Pko\Account\Livewire\Dashboard;
use Pko\Account\Livewire\OrderDetailPage;
use Pko\Account\Livewire\OrdersPage;
use Tests\TestCase;

class OrderStatusDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_le_badge_du_detail_affiche_le_libelle_francais(): void
    {
        [$customer, $user] = $this->makeCustomerWithUser();
        $order = $this->makeOrderForCustomer($customer, 'payment-received');

        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['order' => $order])
            ->assertSee('Paiement reçu');
    }

    public function test_la_liste_et_le_dashboard_affichent_le_libelle_francais(): void
    {
        [$customer, $user] = $this->makeCustomerWithUser();
        $this->makeOrderForCustomer($customer, 'payment-received');

        $this->actingAs($user);

        Livewire::test(OrdersPage::class)
            ->assertSee('Paiement reçu');

        Livewire::test(Dashboard::class)
            ->assertSee('Paiement reçu');
    }

    /** @return array{0: Customer, 1: User} */
    private function makeCustomerWithUser(): array
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create();

        DB::table('lunar_customer_user')->insert([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        return [$customer, $user];
    }

    private function makeOrderForCustomer(Customer $customer, string $status): Order
    {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        $id = DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'customer_id' => $customer->id,
            'status' => $status,
            'reference' => 'TEST-'.uniqid(),
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'exchange_rate' => 1,
            'sub_total' => 0,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 0,
            'tax_breakdown' => '[]',
            'discount_breakdown' => '[]',
            'shipping_breakdown' => '[]',
            'meta' => '[]',
            'placed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }
}
