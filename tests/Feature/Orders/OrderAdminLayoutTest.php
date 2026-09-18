<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Filament\Extensions\OrderPageLayoutExtension;
use App\Livewire\CheckoutPage;
use App\Pipelines\Orders\PropagateCartCustomerNotesPipeline;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Models\Staff;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Pko\ShippingCommon\Models\CarrierShipment;
use Tests\TestCase;

/**
 * Fiche commande admin : blocs Produits / Livraison / Transactions, notes client.
 */
class OrderAdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function actingAsStaff(): void
    {
        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'order-layout-test@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');
    }

    private function makeOrder(array $attributes = []): Order
    {
        $currency = Currency::query()->firstOrFail();

        return Order::factory()->create([
            'status' => 'payment-received',
            'placed_at' => now(),
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'channel_id' => Channel::query()->firstOrFail()->id,
            ...$attributes,
        ])->refresh();
    }

    private function addAddress(Order $order, string $type, string $lineOne): void
    {
        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'type' => $type,
            'line_one' => $lineOne,
            'country_id' => Country::query()->firstOrFail()->id,
        ]);
    }

    public function test_la_colonne_principale_suit_l_ordre_produits_livraison_transactions(): void
    {
        $this->actingAsStaff();
        $order = $this->makeOrder(['pko_customer_notes' => "Livrer le matin\nPortail bleu"]);
        $this->addAddress($order, 'shipping', '12 rue de la Livraison');
        $this->addAddress($order, 'billing', '34 avenue de la Facturation');

        $response = $this->get("/admin/orders/{$order->id}");

        $response->assertOk()
            ->assertSeeInOrder([
                'Produits dans la commande',
                'Notes client',
                'Livrer le matin<br />',
                'Livraison',
                '12 rue de la Livraison',
                'Transactions',
                '34 avenue de la Facturation',
            ], escape: false);

        // Les adresses ont quitté la colonne latérale : une seule occurrence chacune.
        $this->assertSame(1, substr_count((string) $response->getContent(), '12 rue de la Livraison'));
        $this->assertSame(1, substr_count((string) $response->getContent(), '34 avenue de la Facturation'));
    }

    public function test_sans_note_client_un_texte_d_attente_est_affiche(): void
    {
        $this->actingAsStaff();
        $order = $this->makeOrder();

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Aucune note laissée par le client');
    }

    public function test_la_note_client_est_echappee(): void
    {
        $this->actingAsStaff();
        $order = $this->makeOrder(['pko_customer_notes' => '<script>alert(1)</script>']);

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false);
    }

    public function test_le_bloc_livraison_liste_les_envois_transporteur(): void
    {
        $this->actingAsStaff();
        $order = $this->makeOrder();

        CarrierShipment::create([
            'order_id' => $order->id,
            'carrier' => 'chronopost',
            'service_code' => null,
            'tracking_number' => 'XY123456789FR',
            'status' => CarrierShipment::STATUS_CREATED,
        ]);

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('XY123456789FR')
            ->assertSee('Suivre le colis')
            ->assertSee('Fiche de l&#039;envoi', escape: false)
            ->assertSee('Bordereau de remise du '.now()->format('d/m/Y'));
    }

    public function test_l_etiquette_se_telecharge_depuis_le_bloc_livraison(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('labels/etiquette-test.pdf', '%PDF-test');

        $this->actingAsStaff();
        $order = $this->makeOrder();

        $shipment = CarrierShipment::create([
            'order_id' => $order->id,
            'carrier' => 'colissimo',
            'tracking_number' => '6A123456789',
            'label_path' => 'labels/etiquette-test.pdf',
            'status' => CarrierShipment::STATUS_CREATED,
        ]);

        // Filament enveloppe chaque action d'un bloc Actions dans un ActionContainer
        // dont la clé vaut « {statePath}.{nom}Action » (state path vide ici).
        $container = ".download_label_{$shipment->id}Action";

        Livewire::test(ManageOrder::class, ['record' => $order->getKey()])
            ->assertInfolistActionExists($container, "download_label_{$shipment->id}")
            ->callInfolistAction($container, "download_label_{$shipment->id}")
            ->assertFileDownloaded('etiquette-test.pdf');
    }

    public function test_les_totaux_n_affichent_le_remboursement_que_s_il_existe(): void
    {
        $order = $this->makeOrder();

        $labels = array_column(OrderPageLayoutExtension::totalsRows($order), 'label');

        $this->assertContains(__('lunarpanel::order.infolist.total.label'), $labels);
        $this->assertNotContains(__('lunarpanel::order.infolist.refund.label'), $labels);
    }

    public function test_pipeline_copie_la_note_client_du_panier(): void
    {
        $cart = Cart::factory()->create([
            'currency_id' => Currency::query()->firstOrFail()->id,
            'channel_id' => Channel::query()->firstOrFail()->id,
            'meta' => ['pko_customer_notes' => str_repeat('A', 2500)],
        ]);
        $order = $this->makeOrder(['cart_id' => $cart->id]);

        (new PropagateCartCustomerNotesPipeline)->handle($order, fn (Order $o): Order => $o);

        $this->assertSame(PropagateCartCustomerNotesPipeline::MAX_LENGTH, mb_strlen((string) $order->fresh()->pko_customer_notes));
    }

    public function test_checkout_persiste_la_note_client_sans_html(): void
    {
        $cart = Cart::factory()->create([
            'currency_id' => Currency::query()->firstOrFail()->id,
            'channel_id' => Channel::query()->firstOrFail()->id,
            'meta' => [],
        ]);
        CartSession::use($cart);

        Livewire::test(CheckoutPage::class)
            ->set('customerNotes', '<b>Sonner</b> deux fois')
            ->assertSet('customerNotes', 'Sonner deux fois');

        $this->assertSame('Sonner deux fois', CartSession::current()->meta['pko_customer_notes']);
    }
}
