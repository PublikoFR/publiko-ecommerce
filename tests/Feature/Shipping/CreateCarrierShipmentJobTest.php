<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Mockery;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Dto\ShipmentRequest;
use Pko\ShippingCommon\Dto\ShipmentResponse;
use Pko\ShippingCommon\Jobs\CreateCarrierShipmentJob;
use Tests\TestCase;

/**
 * Teste la chaîne order.meta['pickup_point']['id'] → CreateCarrierShipmentJob → ShipmentRequest.pickupPointId.
 *
 * CarrierClient est mocké (aucun appel SOAP réel). Storage et Mail sont faked.
 */
class CreateCarrierShipmentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        Storage::fake('local');
        Mail::fake();

        config(['chronopost.shipper' => [
            'name' => 'Expéditeur Test',
            'street' => '1 rue du Test',
            'zip' => '75001',
            'city' => 'Paris',
            'country' => 'FR',
            'phone' => '0600000000',
            'email' => 'expediteur@test.local',
        ]]);
    }

    public function test_pickup_point_id_est_propage_dans_shipment_request(): void
    {
        $order = $this->makeOrder(meta: [
            'pickup_point' => ['id' => 'PR_MONRELAIS', 'name' => 'Tabac du Centre'],
        ]);

        $capturedRequest = null;
        $this->bindMockCarrierClient('chronopost', function (ShipmentRequest $req) use (&$capturedRequest): ShipmentResponse {
            $capturedRequest = $req;

            return new ShipmentResponse('TRACK001', base64_encode('PDF_CONTENT'));
        });

        (new CreateCarrierShipmentJob($order->id, 'chronopost', 'chrono_relais'))->handle();

        $this->assertNotNull($capturedRequest, 'createShipment() doit avoir été appelé');
        $this->assertSame('PR_MONRELAIS', $capturedRequest->pickupPointId);
        $this->assertSame($order->id, $capturedRequest->orderId);
        $this->assertSame('chrono_relais', $capturedRequest->serviceCode);
    }

    public function test_pickup_point_absent_laisse_pickup_point_id_null_sans_crash(): void
    {
        $order = $this->makeOrder(meta: []); // aucune clé pickup_point

        $capturedRequest = null;
        $this->bindMockCarrierClient('chronopost', function (ShipmentRequest $req) use (&$capturedRequest): ShipmentResponse {
            $capturedRequest = $req;

            return new ShipmentResponse('TRACK002', base64_encode('PDF_CONTENT'));
        });

        (new CreateCarrierShipmentJob($order->id, 'chronopost', 'chrono13'))->handle();

        $this->assertNotNull($capturedRequest, 'createShipment() doit avoir été appelé');
        $this->assertNull($capturedRequest->pickupPointId);
    }

    public function test_pickup_point_id_vide_est_normalise_en_null(): void
    {
        $order = $this->makeOrder(meta: [
            'pickup_point' => ['id' => '', 'name' => 'Sans ID'],
        ]);

        $capturedRequest = null;
        $this->bindMockCarrierClient('chronopost', function (ShipmentRequest $req) use (&$capturedRequest): ShipmentResponse {
            $capturedRequest = $req;

            return new ShipmentResponse('TRACK003', base64_encode('PDF_CONTENT'));
        });

        (new CreateCarrierShipmentJob($order->id, 'chronopost', 'chrono_relais'))->handle();

        $this->assertNotNull($capturedRequest);
        $this->assertNull($capturedRequest->pickupPointId, 'Un id vide doit être normalisé en null');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeOrder(array $meta = []): Order
    {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        $id = \DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'status' => 'awaiting-quote',
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
            'meta' => json_encode($meta),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('lunar_order_addresses')->insert([
            'order_id' => $id,
            'type' => 'shipping',
            'first_name' => 'Jean',
            'last_name' => 'Test',
            'line_one' => '1 rue du Test',
            'postcode' => '75001',
            'city' => 'Paris',
            'contact_email' => 'client@test.local',
            'contact_phone' => '0600000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }

    /**
     * Lie un mock CarrierClient dans le conteneur. andReturnUsing reçoit les args
     * de l'appel → permet de capturer le ShipmentRequest sans double closure.
     */
    private function bindMockCarrierClient(string $carrier, \Closure $onCall): void
    {
        $mock = Mockery::mock(CarrierClient::class);
        $mock->shouldReceive('createShipment')
            ->once()
            ->andReturnUsing($onCall);

        $this->app->bind("pko.shipping.carrier.{$carrier}", fn () => $mock);
    }
}
