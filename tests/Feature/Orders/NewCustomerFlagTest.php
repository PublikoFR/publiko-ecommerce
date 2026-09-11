<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\Jobs\Orders\MarkAsNewCustomer;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Tests\TestCase;

/**
 * Colonne « Type de client » du back-office (`lunar_orders.new_customer`).
 *
 * Le flag est calculé par le job Lunar MarkAsNewCustomer, dispatché DANS la
 * transaction de CreateOrder. La colonne vaut `false` par défaut : un job perdu
 * n'échoue pas, il laisse simplement « Retour » sur la commande d'un client qui
 * commande pour la première fois. D'où les deux vérifications ci-dessous —
 * le calcul lui-même, et le garde-fou de config qui garantit que le job est
 * bien poussé après le commit.
 */
class NewCustomerFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_les_jobs_sont_pousses_apres_le_commit(): void
    {
        // Sans after_commit, un worker rapide consomme MarkAsNewCustomer avant le
        // commit de CreateOrder : Order::find() ne trouve rien, le job sort sans
        // erreur et la commande reste marquée « Retour ». Intermittent par nature.
        foreach (['redis', 'database'] as $connection) {
            $this->assertTrue(
                (bool) config("queue.connections.{$connection}.after_commit"),
                "La connexion queue [{$connection}] doit dispatcher après le commit.",
            );
        }
    }

    public function test_une_premiere_commande_est_marquee_nouveau_client(): void
    {
        $order = $this->makeOrder('premiere@example.test');

        (new MarkAsNewCustomer($order->id))->handle();

        $this->assertTrue((bool) $order->fresh()->new_customer);
    }

    public function test_une_commande_suivante_du_meme_email_est_marquee_retour(): void
    {
        $this->makeOrder('recurrent@example.test', now()->subMonth());
        $second = $this->makeOrder('recurrent@example.test');

        (new MarkAsNewCustomer($second->id))->handle();

        $this->assertFalse((bool) $second->fresh()->new_customer);
    }

    public function test_un_autre_email_reste_nouveau_client(): void
    {
        $this->makeOrder('autre@example.test', now()->subMonth());
        $order = $this->makeOrder('premiere-fois@example.test');

        (new MarkAsNewCustomer($order->id))->handle();

        $this->assertTrue((bool) $order->fresh()->new_customer);
    }

    private function makeOrder(string $email, ?\DateTimeInterface $placedAt = null): Order
    {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        $id = DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'status' => 'payment-received',
            'reference' => 'TEST-'.uniqid(),
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'exchange_rate' => 1,
            'sub_total' => 10000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 10000,
            'tax_breakdown' => '[]',
            'discount_breakdown' => '[]',
            'shipping_breakdown' => '[]',
            'meta' => '[]',
            'placed_at' => $placedAt ?? now(),
            'created_at' => $placedAt ?? now(),
            'updated_at' => now(),
        ]);

        DB::table('lunar_order_addresses')->insert([
            'order_id' => $id,
            'type' => 'billing',
            'first_name' => 'Test',
            'last_name' => 'Client',
            'line_one' => '1 rue du Test',
            'postcode' => '34500',
            'city' => 'Béziers',
            'country_id' => Country::query()->where('iso2', 'FR')->value('id'),
            'contact_email' => $email,
            'contact_phone' => '0600000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }
}
