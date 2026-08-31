<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\CartLine;
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

    public function test_purge_deletes_customer_without_orders(): void
    {
        $user = User::create([
            'name' => 'Marie Martin',
            'email' => 'marie@example.test',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create([
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'company_name' => 'Prospect SARL',
        ]);
        $customer->users()->attach($user);

        // Cart::delete() est un soft-delete (SoftDeletes) : la ligne reste en
        // base avec sa FK user_id/customer_id active si on ne force pas.
        $cart = Cart::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        // Aucune commande → suppression physique complète.
        $result = app(AnonymizeCustomer::class)->purge($customer);

        $this->assertSame('deleted', $result);
        $this->assertNull(Customer::find($customer->id), 'Le client sans commande doit être supprimé.');
        $this->assertNull(User::find($user->id), 'Le compte de connexion doit être supprimé.');
        $this->assertNull(Cart::withTrashed()->find($cart->id), 'Le panier doit être supprimé physiquement (forceDelete).');
    }

    public function test_purge_deletes_carts_with_addresses_and_lines(): void
    {
        $user = User::create([
            'name' => 'Luc Petit',
            'email' => 'luc@example.test',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create([
            'first_name' => 'Luc',
            'last_name' => 'Petit',
            'company_name' => 'Panier SARL',
        ]);
        $customer->users()->attach($user);

        // Panier actif, avec adresse + ligne : les FK NO ACTION de
        // lunar_cart_addresses / lunar_cart_lines bloquent le forceDelete si
        // elles ne sont pas dénouées d'abord (erreur SQL 1451).
        $cart = Cart::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);
        CartAddress::factory()->create(['cart_id' => $cart->id, 'type' => 'shipping']);
        CartLine::factory()->create(['cart_id' => $cart->id]);

        // Panier déjà soft-deleted et fusionné dans le panier courant : sa FK
        // user_id reste active en base, et sa self-FK merged_id aussi.
        $mergedCart = Cart::factory()->create(['user_id' => $user->id, 'merged_id' => $cart->id]);
        $mergedCart->delete();

        $result = app(AnonymizeCustomer::class)->purge($customer);

        $this->assertSame('deleted', $result);
        $this->assertNull(Customer::find($customer->id));
        $this->assertNull(User::find($user->id));
        $this->assertNull(Cart::withTrashed()->find($cart->id));
        $this->assertNull(Cart::withTrashed()->find($mergedCart->id));
        $this->assertSame(0, CartAddress::where('cart_id', $cart->id)->count());
        $this->assertSame(0, CartLine::where('cart_id', $cart->id)->count());
    }

    public function test_purge_anonymizes_customer_with_orders(): void
    {
        $customer = Customer::create([
            'first_name' => 'Paul',
            'last_name' => 'Durand',
            'company_name' => 'Client Actif SARL',
        ]);
        Order::factory()->create(['customer_id' => $customer->id]);

        $result = app(AnonymizeCustomer::class)->purge($customer);

        $this->assertSame('anonymized', $result);
        $fresh = Customer::find($customer->id);
        $this->assertNotNull($fresh, 'Le client avec commande doit être conservé (compta).');
        $this->assertSame('anonymisé', $fresh->last_name);
        $this->assertNotNull($fresh->anonymized_at, 'anonymized_at doit être horodaté.');
    }
}
