<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Filament\Extensions\OrderPageLayoutExtension;
use App\Livewire\CheckoutPage;
use App\Pipelines\Orders\PropagateCartCustomerNotesPipeline;
use Database\Seeders\DatabaseSeeder;
use Filament\Infolists\Components\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\Components\OrderItemsTable;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Livewire\Components\ActivityLogFeed;
use Lunar\Admin\Models\Staff;
use Lunar\Admin\Support\ActivityLog\Orders\StatusUpdate;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Support\CarrierLabelUrl;
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

    public function test_l_etiquette_s_ouvre_dans_un_onglet_via_un_lien_signe(): void
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

        $html = $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Voir l&#039;étiquette', escape: false)
            ->getContent();

        // Lien signé vers l'étiquette, ouvert dans un nouvel onglet.
        $this->assertMatchesRegularExpression(
            '#href="[^"]*/admin/expedition/etiquettes/'.$shipment->id.'/pdf\?[^"]*signature=[^"]*"[^>]*target="_blank"#s',
            $html,
        );

        $url = CarrierLabelUrl::for($shipment);

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('inline;', (string) $this->get($url)->headers->get('Content-Disposition'));
    }

    public function test_le_lien_d_etiquette_exige_signature_et_session_staff(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('labels/etiquette-test.pdf', '%PDF-test');

        $shipment = CarrierShipment::create([
            'order_id' => $this->makeOrder()->id,
            'carrier' => 'colissimo',
            'label_path' => 'labels/etiquette-test.pdf',
            'status' => CarrierShipment::STATUS_CREATED,
        ]);

        $url = CarrierLabelUrl::for($shipment);

        // Sans session staff : pas de PDF, même avec un lien signé valide.
        $this->get($url)->assertRedirect();

        $this->actingAsStaff();

        // Session staff mais lien non signé ou altéré.
        $this->get("/admin/expedition/etiquettes/{$shipment->id}/pdf")->assertForbidden();
        $this->get(str_replace("/{$shipment->id}/", '/'.($shipment->id + 1).'/', (string) $url))->assertForbidden();
    }

    public function test_les_lignes_affichent_quantite_x_prix_unitaire(): void
    {
        $this->actingAsStaff();
        $order = $this->makeOrder();

        OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => ProductVariant::morphName(),
            'purchasable_id' => ProductVariant::factory()->create()->id,
            'description' => 'Sachet de visserie',
            'quantity' => 2,
            'unit_price' => 324,
            'sub_total' => 648,
        ]);

        Livewire::test(OrderItemsTable::class, ['record' => $order])
            ->assertSee('2 x 3,24')
            ->assertDontSee('2 @');
    }

    public function test_l_historique_remplace_les_etiquettes_dans_la_colonne_laterale(): void
    {
        $aside = ManageOrder::getInfolistAsideSchema();
        $headings = array_map(
            fn ($component): ?string => $component instanceof Section ? (string) $component->getHeading() : null,
            $aside,
        );

        $this->assertNotContains(__('lunarpanel::order.infolist.tags.label'), $headings);
        $this->assertContains(__('lunarpanel::order.infolist.timeline.label'), $headings);

        $main = array_map(
            fn ($component): ?string => $component instanceof Section ? (string) $component->getHeading() : null,
            ManageOrder::getInfolistSchema(),
        );
        $this->assertNotContains(__('lunarpanel::order.infolist.timeline.label'), $main);
    }

    public function test_la_vue_d_ensemble_regroupe_reference_statut_date_et_client(): void
    {
        $this->actingAsStaff();
        $customer = Customer::factory()->create(['title' => null, 'first_name' => 'Sophie', 'last_name' => 'Girard']);
        $order = $this->makeOrder([
            'customer_id' => $customer->id,
            'reference' => 'TST-OVERVIEW-1',
            'placed_at' => '2026-07-19 10:34:00',
            'new_customer' => false,
            'customer_reference' => null,
        ]);
        $this->makeOrder(['customer_id' => $customer->id]);

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSeeInOrder(['TST-OVERVIEW-1', 'Paiement reçu', 'Passée le 19/07/2026 à 10:34', 'Sophie Girard', 'Client récurrent · 2 commandes', 'Fiche client'])
            ->assertDontSee('View customer')
            ->assertDontSee('Réf. client');
    }

    public function test_le_badge_de_statut_ouvre_la_modale_de_changement_de_statut(): void
    {
        $this->actingAsStaff();
        $order = $this->makeOrder();

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('wire:click="mountAction(&#039;update_status&#039;)"', escape: false);

        Livewire::test(ManageOrder::class, ['record' => $order->getKey()])
            ->call('mountAction', 'update_status')
            ->assertActionMounted('update_status');
    }

    public function test_le_groupe_d_alertes_vide_est_masque(): void
    {
        $this->actingAsStaff();
        $order = $this->makeOrder();

        $infolist = Livewire::test(ManageOrder::class, ['record' => $order->getKey()])
            ->instance()
            ->getInfolist('infolist');

        $shouts = collect($infolist->getFlatComponents(withHidden: true))
            ->first(fn ($component): bool => $component->getKey() === 'shouts');

        $this->assertNotNull($shouts);
        $this->assertTrue($shouts->isHidden());
    }

    public function test_la_chronologie_place_le_bouton_dans_le_flux_et_date_en_francais(): void
    {
        $this->actingAsStaff();
        $order = $this->makeOrder();

        activity()->performedOn($order)->event('updated')->log('updated');

        $expectedDate = ucfirst(now()->locale('fr')->translatedFormat('l j F Y'));

        Livewire::test(ActivityLogFeed::class, ['subject' => $order])
            ->assertSee('Ajouter un commentaire')
            ->assertSee($expectedDate)
            ->assertDontSee('absolute right-0 mt-2', escape: false);
    }

    public function test_un_changement_de_statut_affiche_les_deux_libelles_sous_le_titre(): void
    {
        $order = $this->makeOrder();

        $log = activity()
            ->performedOn($order)
            ->event('status-update')
            ->withProperties(['previous' => 'awaiting-payment', 'new' => 'payment-received'])
            ->log('status-update');

        $html = (string) (new StatusUpdate)->render($log)->render();

        $this->assertStringContainsString('flex-wrap', $html);
        $this->assertMatchesRegularExpression('/En attente de paiement.*Paiement reçu/s', $html);
    }

    public function test_la_vue_d_ensemble_affiche_la_raison_sociale_au_dessus_du_client(): void
    {
        $this->actingAsStaff();
        $customer = Customer::factory()->create([
            'title' => null, 'first_name' => 'Sophie', 'last_name' => 'Girard', 'company_name' => 'Compte SARL',
        ]);
        $order = $this->makeOrder(['customer_id' => $customer->id]);
        $this->addAddress($order, 'billing', '34 avenue de la Facturation');
        // Saisie libre sur l'adresse : seule la raison sociale du compte (vérifiée SIRET) fait foi.
        $order->billingAddress()->update(['company_name' => 'Adresse Libre SAS']);

        $this->assertSame('Compte SARL', OrderPageLayoutExtension::overview($order->refresh())['company']);

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSeeInOrder(['Compte SARL', 'Sophie Girard', 'Fiche client']);
    }

    public function test_une_commande_sans_compte_n_a_pas_de_raison_sociale(): void
    {
        $order = $this->makeOrder(['customer_id' => null]);
        $this->addAddress($order, 'billing', '34 avenue de la Facturation');
        $order->billingAddress()->update(['company_name' => 'Adresse Libre SAS']);

        $this->assertNull(OrderPageLayoutExtension::overview($order->refresh())['company']);
    }

    public function test_la_vue_d_ensemble_affiche_les_details_renseignes(): void
    {
        $order = $this->makeOrder(['pko_site_name' => 'Résidence Les Pins', 'customer_reference' => 'BC-2231']);

        $details = OrderPageLayoutExtension::overview($order)['details'];

        $this->assertSame(['Chantier', 'Réf. client'], array_column($details, 'label'));
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
