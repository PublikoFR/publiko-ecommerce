<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Pko\CustomerAuth\Actions\AnonymizeCustomer;
use Tests\TestCase;

class AnonymizeCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_anonymize_keeps_customer_and_orders_but_removes_personal_data_and_login(): void
    {
        $user = User::create([
            'name' => 'Jean Dupont',
            'email' => 'jean@example.test',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'ACME SARL',
            'tax_identifier' => 'FR12345678900',
            'sirene_status' => 'active',
        ]);

        $group = CustomerGroup::firstOrCreate(['handle' => 'installateurs'], ['name' => 'Installateurs']);
        $customer->customerGroups()->attach($group);
        $customer->users()->attach($user);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        app(AnonymizeCustomer::class)->handle($customer);

        // La fiche client est CONSERVÉE mais anonymisée.
        $fresh = Customer::find($customer->id);
        $this->assertNotNull($fresh, 'Le client ne doit pas être supprimé (compta).');
        $this->assertSame('Client', $fresh->first_name);
        $this->assertSame('anonymisé', $fresh->last_name);
        $this->assertNull($fresh->tax_identifier);
        $this->assertNull($fresh->sirene_status);
        $this->assertSame(0, $fresh->customerGroups()->count());

        // La commande est CONSERVÉE, rattachée au client anonymisé, user_id dénoué.
        $freshOrder = Order::find($order->id);
        $this->assertNotNull($freshOrder, 'La commande doit être conservée.');
        $this->assertSame($customer->id, (int) $freshOrder->customer_id);
        $this->assertNull($freshOrder->user_id);

        // Le compte de connexion est supprimé.
        $this->assertNull(User::find($user->id));
    }
}
