<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Pko\ShippingCommon\Jobs\CreateCarrierShipmentJob;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Shipping\ShipmentLabelService;
use RuntimeException;
use Tests\Stubs\FakeCarrierClient;
use Tests\TestCase;
use Throwable;

/**
 * Envois transporteur : enregistrement à l'encaissement, étiquette à la demande.
 *
 * L'étiquette n'est plus jamais créée automatiquement : l'observer et le
 * rattrapage n'enregistrent que des envois « en attente », sans appel
 * transporteur ; l'admin crée l'étiquette depuis la fiche commande.
 */
class OrderShipmentObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        Storage::fake('local');
    }

    public function test_lencaissement_enregistre_un_envoi_en_attente_sans_etiquette(): void
    {
        $order = $this->makeOrder('awaiting-payment');

        $order->update(['status' => 'payment-received']);

        Queue::assertNotPushed(CreateCarrierShipmentJob::class);
        $this->assertDatabaseHas('pko_carrier_shipments', [
            'order_id' => $order->id,
            'carrier' => 'chronopost',
            'service_code' => 'chrono_relais',
            'origin' => CarrierShipment::ORIGIN_WEKLO,
            'status' => CarrierShipment::STATUS_PENDING,
            'tracking_number' => null,
        ]);
    }

    public function test_le_rattrapage_enregistre_lenvoi_dune_commande_payee(): void
    {
        // L'observer ne réagit qu'à une transition de statut, et les adresses
        // n'existent pas encore à la création : une commande importée déjà payée
        // n'a donc jamais d'envoi. C'est le rôle de la commande de rattrapage.
        $order = $this->makeOrder('payment-received');

        $this->artisan('shipping:backfill-shipments')->assertSuccessful();

        Queue::assertNotPushed(CreateCarrierShipmentJob::class);
        $this->assertSame(
            CarrierShipment::STATUS_PENDING,
            CarrierShipment::query()->where('order_id', $order->id)->value('status'),
        );
    }

    public function test_le_rattrapage_ignore_une_commande_deja_pourvue(): void
    {
        $order = $this->makeOrder('awaiting-payment');
        $order->update(['status' => 'payment-received']);

        $this->artisan('shipping:backfill-shipments')
            ->expectsOutputToContain('Aucune commande à rattraper.')
            ->assertSuccessful();
    }

    public function test_aucun_envoi_sans_option_de_livraison(): void
    {
        $order = $this->makeOrder('awaiting-payment', shippingOption: null);

        $order->update(['status' => 'payment-received']);

        $this->assertDatabaseMissing('pko_carrier_shipments', ['order_id' => $order->id]);
    }

    public function test_pas_de_doublon_quand_le_statut_est_reecrit(): void
    {
        $order = $this->makeOrder('awaiting-payment');

        $order->update(['status' => 'payment-received']);
        $order->update(['status' => 'awaiting-payment']);
        $order->update(['status' => 'payment-received']);

        $this->assertSame(1, CarrierShipment::query()->where('order_id', $order->id)->count());
    }

    public function test_la_creation_manuelle_genere_letiquette(): void
    {
        $client = $this->fakeCarrier();
        $order = $this->makeOrder('payment-received');

        $shipment = app(ShipmentLabelService::class)->createLabel($order, CarrierShipment::ORIGIN_WEKLO);

        $this->assertSame(CarrierShipment::STATUS_CREATED, $shipment->status);
        $this->assertSame('XN000000001FR', $shipment->tracking_number);
        $this->assertNotNull($shipment->payload_sent);
        Storage::disk('local')->assertExists((string) $shipment->label_path);
        $this->assertSame('chrono_relais', $client->requests[0]->serviceCode);
        $this->assertSame([], app(ShipmentLabelService::class)->originsAwaitingLabel($order));
    }

    public function test_un_echec_de_creation_est_trace_sur_lenvoi(): void
    {
        $this->fakeCarrier(new RuntimeException('Chronopost : erreur 38 — No routing found'));
        $order = $this->makeOrder('payment-received');

        try {
            app(ShipmentLabelService::class)->createLabel($order, CarrierShipment::ORIGIN_WEKLO);
            $this->fail('Une exception était attendue.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No routing found', $e->getMessage());
        }

        $shipment = CarrierShipment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(CarrierShipment::STATUS_FAILED, $shipment->status);
        $this->assertStringContainsString('No routing found', (string) $shipment->error_message);
        // Toujours à créer : le bouton reste proposé (libellé « Recréer »).
        $this->assertSame([CarrierShipment::ORIGIN_WEKLO], app(ShipmentLabelService::class)->originsAwaitingLabel($order));
    }

    public function test_le_menu_actions_de_la_fiche_commande_cree_letiquette(): void
    {
        $this->fakeCarrier();
        $order = $this->makeOrder('payment-received');
        $this->actingAsStaff();

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Créer l&#039;étiquette Chronopost', false);

        $component = Livewire::test(ManageOrder::class, ['record' => $order->id])
            ->callAction('create_label_weklo')
            ->assertNotified('Étiquette créée');

        // L'étiquette s'ouvre aussitôt dans un nouvel onglet, via le lien signé.
        $js = json_encode($component->effects['xjs'] ?? []);
        $this->assertStringContainsString('window.open', $js);
        $this->assertStringContainsString('etiquettes', $js);
        $this->assertStringContainsString('signature=', $js);

        $this->assertSame(
            'XN000000001FR',
            CarrierShipment::query()->where('order_id', $order->id)->value('tracking_number'),
        );
    }

    public function test_la_section_livraison_cree_letiquette(): void
    {
        $this->fakeCarrier();
        $order = $this->makeOrder('payment-received');
        $this->actingAsStaff();

        // Filament enveloppe chaque action d'un bloc Actions dans un ActionContainer
        // dont la clé vaut « {statePath}.{nom}Action » (state path vide ici).
        Livewire::test(ManageOrder::class, ['record' => $order->id])
            ->assertInfolistActionExists('.create_label_wekloAction', 'create_label_weklo')
            ->callInfolistAction('.create_label_wekloAction', 'create_label_weklo')
            ->assertNotified('Étiquette créée');

        $this->assertSame(
            CarrierShipment::STATUS_CREATED,
            CarrierShipment::query()->where('order_id', $order->id)->value('status'),
        );
    }

    public function test_le_raccourci_etiquette_du_bloc_commande(): void
    {
        $this->fakeCarrier();
        $order = $this->makeOrder('payment-received');
        $this->actingAsStaff();

        // Avant création : « À créer » + bouton relié à l'action du menu Actions.
        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Étiquette Chronopost')
            ->assertSee('À créer')
            ->assertSee("mountAction('create_label_weklo')", false);

        Livewire::test(ManageOrder::class, ['record' => $order->id])
            ->mountAction('create_label_weklo')
            ->callMountedAction()
            ->assertNotified('Étiquette créée');

        // Après création : n° de suivi, « Voir » (onglet) et « PDF » (téléchargement), signés.
        $html = $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('N° XN000000001FR')
            ->assertDontSee('À créer')
            ->getContent();

        $this->assertMatchesRegularExpression('#/admin/expedition/etiquettes/\d+/pdf\?download=1&(amp;)?expires=\d+&(amp;)?signature=#', $html);
    }

    private function fakeCarrier(?Throwable $failure = null): FakeCarrierClient
    {
        $client = new FakeCarrierClient($failure);
        $this->app->instance('pko.shipping.carrier.chronopost', $client);

        return $client;
    }

    private function actingAsStaff(): void
    {
        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'label-test@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');
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
