<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Pko\ShippingCommon\Pipelines\ApplyPickupPointAddress;
use Tests\TestCase;

/**
 * Livraison en point relais : la commande doit porter l'adresse du relais, pas
 * celle du client — le colis y est livré, et le back-office affichait l'adresse
 * du client comme destination.
 */
class PickupPointOrderAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_ladresse_de_livraison_devient_celle_du_point_relais(): void
    {
        $order = $this->makeOrder([
            'pickup_point' => [
                'id' => '056DL',
                'name' => 'POKE STORE',
                'address1' => '45 allées Paul Riquet',
                'postcode' => '34500',
                'city' => 'BEZIERS',
                'country_code' => 'FR',
            ],
        ]);

        $order = $this->runPipeline($order);

        $address = $order->shippingAddress;
        $this->assertSame('POKE STORE', $address->company_name);
        $this->assertSame('45 allées Paul Riquet', $address->line_one);
        $this->assertSame('BEZIERS', $address->city);
        $this->assertSame('34500', $address->postcode);

        // Le destinataire final reste identifiable par le point relais.
        $this->assertSame('Romain', $address->first_name);
        $this->assertSame('GALVEZ', $address->last_name);
        $this->assertSame('riderfx3@gmail.com', $address->contact_email);

        // L'adresse du client est conservée pour le suivi et les retours.
        $original = $order->fresh()->meta['delivery_address_original'] ?? null;
        $this->assertSame('54 Rue des Châtaigniers', $original['line_one'] ?? null);
    }

    public function test_une_commande_sans_point_relais_nest_pas_touchee(): void
    {
        $order = $this->runPipeline($this->makeOrder([]));

        $this->assertSame('54 Rue des Châtaigniers', $order->shippingAddress->line_one);
        $this->assertArrayNotHasKey('delivery_address_original', (array) $order->meta);
    }

    public function test_le_pipeline_est_idempotent(): void
    {
        $meta = [
            'pickup_point' => [
                'id' => '056DL',
                'name' => 'POKE STORE',
                'address1' => '45 allées Paul Riquet',
                'postcode' => '34500',
                'city' => 'BEZIERS',
            ],
        ];

        $order = $this->runPipeline($this->makeOrder($meta));
        $order = $this->runPipeline($order);

        // Un second passage ne doit pas prendre l'adresse du relais pour l'originale.
        $original = $order->fresh()->meta['delivery_address_original'] ?? null;
        $this->assertSame('54 Rue des Châtaigniers', $original['line_one'] ?? null);
    }

    private function runPipeline(Order $order): Order
    {
        return (new ApplyPickupPointAddress)->handle($order, fn (Order $o): Order => $o);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function makeOrder(array $meta): Order
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
            'sub_total' => 60000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 60000,
            'tax_breakdown' => '[]',
            'discount_breakdown' => '[]',
            'shipping_breakdown' => '[]',
            'meta' => json_encode($meta),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lunar_order_addresses')->insert([
            'order_id' => $id,
            'type' => 'shipping',
            'first_name' => 'Romain',
            'last_name' => 'GALVEZ',
            'line_one' => '54 Rue des Châtaigniers',
            'postcode' => '34500',
            'city' => 'Béziers',
            // L'observer table-rate résout une zone depuis le pays à chaque
            // sauvegarde d'adresse : sans pays, il lève un TypeError.
            'country_id' => Country::query()->where('iso2', 'FR')->value('id'),
            'contact_email' => 'riderfx3@gmail.com',
            'contact_phone' => '0785942940',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }
}
