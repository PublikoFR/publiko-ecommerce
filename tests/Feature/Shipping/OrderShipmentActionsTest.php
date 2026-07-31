<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Pko\ShippingCommon\Models\CarrierShipment;
use Tests\TestCase;

/**
 * Raccourcis d'expédition sur la fiche commande : étiquette, envoi, bordereau du jour.
 *
 * Le chemin commande → expédition manquait ; seul le chemin inverse existait.
 */
class OrderShipmentActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        Storage::fake('local');

        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'order-shipping-test@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');
    }

    public function test_la_fiche_commande_expose_letiquette_et_le_bordereau_du_jour(): void
    {
        $orderId = $this->makeOrderId();

        Storage::disk('local')->put("labels/{$orderId}/chronopost-LT-9.pdf", '%PDF-1.4 fake');

        CarrierShipment::create([
            'order_id' => $orderId,
            'carrier' => 'chronopost',
            'service_code' => 'chrono13',
            'origin' => CarrierShipment::ORIGIN_WEKLO,
            'status' => CarrierShipment::STATUS_CREATED,
            'tracking_number' => 'LT-9',
            'label_path' => "labels/{$orderId}/chronopost-LT-9.pdf",
        ]);

        $response = $this->get("/admin/orders/{$orderId}");

        $response->assertOk();
        $response->assertSee('Expédition');
        $response->assertSee('Envoi n° LT-9');
        $response->assertSee('Bordereau du '.now()->format('d/m/Y'));
    }

    public function test_aucune_action_expedition_sur_une_commande_sans_envoi(): void
    {
        $orderId = $this->makeOrderId();

        $response = $this->get("/admin/orders/{$orderId}");

        $response->assertOk();
        $response->assertDontSee('Bordereau du ');
        $response->assertDontSee('Point relais :');
    }

    public function test_le_point_relais_est_lisible_meme_sans_etiquette(): void
    {
        // Cas courant tant que le worker de queue n'a pas consommé le job :
        // la commande désigne un relais mais aucun envoi n'existe encore.
        $orderId = $this->makeOrderId([
            'pickup_point' => [
                'id' => '056DL',
                'name' => 'POKE STORE',
                'address1' => '45 allées Paul Riquet',
                'postcode' => '34500',
                'city' => 'BEZIERS',
            ],
        ]);

        $response = $this->get("/admin/orders/{$orderId}");

        $response->assertOk();
        $response->assertSee('Point relais : POKE STORE (056DL)');
        $response->assertSee('Aucune étiquette générée');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function makeOrderId(array $meta = []): int
    {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        return (int) DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'status' => 'dispatched',
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
            'placed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
