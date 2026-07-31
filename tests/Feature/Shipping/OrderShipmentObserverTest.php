<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Pko\ShippingCommon\Jobs\CreateCarrierShipmentJob;
use Pko\ShippingCommon\Models\CarrierShipment;
use Tests\TestCase;

/**
 * Déclenchement de la création d'étiquette à l'encaissement.
 *
 * Aucun envoi transporteur n'était généré en dev alors que les commandes
 * atteignaient bien `payment-received` : ce test cadre le contrat de l'observer.
 */
class OrderShipmentObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
    }

    public function test_le_passage_a_payment_received_declenche_la_creation_detiquette(): void
    {
        $order = $this->makeOrder('awaiting-payment');

        $order->update(['status' => 'payment-received']);

        Queue::assertPushed(CreateCarrierShipmentJob::class);
    }

    public function test_le_rattrapage_couvre_une_commande_payee_sans_envoi(): void
    {
        // L'observer ne réagit qu'à une transition de statut, et les adresses
        // n'existent pas encore à la création : une commande importée déjà payée
        // n'a donc jamais d'étiquette. C'est le rôle de la commande de rattrapage.
        $this->makeOrder('payment-received');

        $this->artisan('shipping:backfill-shipments')->assertSuccessful();

        Queue::assertPushed(CreateCarrierShipmentJob::class);
    }

    public function test_le_rattrapage_ignore_une_commande_deja_pourvue(): void
    {
        $order = $this->makeOrder('awaiting-payment');
        $order->update(['status' => 'payment-received']);

        CarrierShipment::create([
            'order_id' => $order->id,
            'carrier' => 'chronopost',
            'service_code' => 'chrono_relais',
            'origin' => CarrierShipment::ORIGIN_WEKLO,
            'status' => CarrierShipment::STATUS_CREATED,
            'tracking_number' => 'LT-DEJA-LA',
        ]);

        $this->artisan('shipping:backfill-shipments')
            ->expectsOutputToContain('Aucune commande à rattraper.')
            ->assertSuccessful();
    }

    public function test_aucun_declenchement_sans_option_de_livraison(): void
    {
        $order = $this->makeOrder('awaiting-payment', shippingOption: null);

        $order->update(['status' => 'payment-received']);

        Queue::assertNotPushed(CreateCarrierShipmentJob::class);
    }

    public function test_pas_de_doublon_quand_le_statut_est_reecrit(): void
    {
        $order = $this->makeOrder('awaiting-payment');

        $order->update(['status' => 'payment-received']);
        $order->update(['status' => 'payment-received']);

        Queue::assertPushed(CreateCarrierShipmentJob::class, 1);
    }

    private function makeOrder(string $status, ?string $shippingOption = 'chronopost.chrono_relais'): Order
    {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        $id = DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'status' => $status,
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
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variant = ProductVariant::query()->firstOrFail();

        DB::table('lunar_order_lines')->insert([
            'order_id' => $id,
            'purchasable_type' => 'product_variant',
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'description' => 'Produit de test',
            'identifier' => (string) $variant->sku,
            'unit_price' => 60000,
            'unit_quantity' => 1,
            'quantity' => 1,
            'sub_total' => 60000,
            'discount_total' => 0,
            'tax_breakdown' => '[]',
            'tax_total' => 0,
            'total' => 60000,
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
            'country_id' => Country::query()->where('iso2', 'FR')->value('id'),
            'contact_email' => 'riderfx3@gmail.com',
            'shipping_option' => $shippingOption,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Adresse de facturation : elle n'a jamais de `shipping_option`. Sa présence
        // dans le fixture verrouille le filtre du rattrapage, qui l'excluait à tort.
        DB::table('lunar_order_addresses')->insert([
            'order_id' => $id,
            'type' => 'billing',
            'first_name' => 'Romain',
            'last_name' => 'GALVEZ',
            'line_one' => '54 Rue des Châtaigniers',
            'postcode' => '34500',
            'city' => 'Béziers',
            'country_id' => Country::query()->where('iso2', 'FR')->value('id'),
            'contact_email' => 'riderfx3@gmail.com',
            'shipping_option' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }
}
