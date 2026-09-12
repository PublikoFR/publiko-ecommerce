<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Actions\CreateSplitQuoteOrder;
use App\Livewire\CheckoutPage;
use App\Models\User;
use App\Pipelines\Orders\PropagateCartSiteNamePipeline;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Pko\Account\Livewire\OrderDetailPage;
use Tests\TestCase;

class SiteNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeMinimalOrder(string $status = 'awaiting-payment', ?string $siteName = null): Order
    {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        $id = DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'status' => $status,
            'reference' => 'TEST-'.uniqid(),
            'pko_site_name' => $siteName,
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'exchange_rate' => 1,
            'sub_total' => 0,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 0,
            'tax_breakdown' => '[]',
            'discount_breakdown' => '[]',
            'shipping_breakdown' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }

    private function makeOrderForCustomer(Customer $customer, string $status = 'payment-received', ?string $siteName = null): Order
    {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        $id = DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'customer_id' => $customer->id,
            'status' => $status,
            'reference' => 'TEST-'.uniqid(),
            'pko_site_name' => $siteName,
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'exchange_rate' => 1,
            'sub_total' => 0,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 0,
            'tax_breakdown' => '[]',
            'discount_breakdown' => '[]',
            'shipping_breakdown' => '[]',
            'meta' => '[]',
            'placed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }

    private function makeCustomerWithUser(): array
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create();

        DB::table('lunar_customer_user')->insert([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        return [$customer, $user];
    }

    private function makeCartWithMeta(array $meta): Cart
    {
        $currency = Currency::query()->first();
        $channel = Channel::query()->first();

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
            'channel_id' => $channel->id,
            'meta' => $meta,
        ]);

        CartSession::use($cart);

        return $cart;
    }

    // ── Pipeline tests ────────────────────────────────────────────────────────

    public function test_pipeline_copie_pko_site_name_depuis_cart_meta(): void
    {
        $currency = Currency::query()->first();
        $channel = Channel::query()->first();

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
            'channel_id' => $channel->id,
            'meta' => ['pko_site_name' => 'Chantier Résidence Les Pins'],
        ]);

        $order = $this->makeMinimalOrder();
        // Manually link the cart to the order for the pipeline
        DB::table('lunar_orders')->where('id', $order->id)->update(['cart_id' => $cart->id]);
        $order->refresh();

        $pipe = new PropagateCartSiteNamePipeline;
        $result = null;
        $pipe->handle($order, function (Order $o) use (&$result): Order {
            $result = $o;

            return $o;
        });

        $this->assertSame('Chantier Résidence Les Pins', $order->fresh()->pko_site_name);
    }

    public function test_pipeline_ignore_cart_sans_site_name(): void
    {
        $currency = Currency::query()->first();
        $channel = Channel::query()->first();

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
            'channel_id' => $channel->id,
            'meta' => [],
        ]);

        $order = $this->makeMinimalOrder();
        DB::table('lunar_orders')->where('id', $order->id)->update(['cart_id' => $cart->id]);
        $order->refresh();

        $pipe = new PropagateCartSiteNamePipeline;
        $pipe->handle($order, function (Order $o): Order { return $o; });

        $this->assertNull($order->fresh()->pko_site_name);
    }

    public function test_pipeline_tronque_site_name_trop_long(): void
    {
        $currency = Currency::query()->first();
        $channel = Channel::query()->first();

        $longName = str_repeat('A', 300);

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
            'channel_id' => $channel->id,
            'meta' => ['pko_site_name' => $longName],
        ]);

        $order = $this->makeMinimalOrder();
        DB::table('lunar_orders')->where('id', $order->id)->update(['cart_id' => $cart->id]);
        $order->refresh();

        $pipe = new PropagateCartSiteNamePipeline;
        $pipe->handle($order, function (Order $o): Order { return $o; });

        $this->assertSame(255, mb_strlen((string) $order->fresh()->pko_site_name));
    }

    // ── CreateSplitQuoteOrder tests ───────────────────────────────────────────

    public function test_create_split_quote_order_propage_pko_site_name(): void
    {
        $payableOrder = $this->makeMinimalOrder('payment-received', 'Chantier Centre Commercial');

        $quoteOrder = app(CreateSplitQuoteOrder::class)->execute(
            payableOrder: $payableOrder,
            splitPending: [],
            splitGroup: 'test-group-'.uniqid(),
        );

        $this->assertSame('Chantier Centre Commercial', $quoteOrder->pko_site_name);
    }

    public function test_create_split_quote_order_sans_site_name(): void
    {
        $payableOrder = $this->makeMinimalOrder('payment-received', null);

        $quoteOrder = app(CreateSplitQuoteOrder::class)->execute(
            payableOrder: $payableOrder,
            splitPending: [],
            splitGroup: 'test-group-'.uniqid(),
        );

        $this->assertNull($quoteOrder->pko_site_name);
    }

    // ── Checkout Livewire tests ───────────────────────────────────────────────

    public function test_checkout_persiste_site_name_dans_cart_meta(): void
    {
        // DatabaseSeeder fournit déjà la France — pas de Country::factory() ici.
        $cart = $this->makeCartWithMeta([]);

        // Livewire 3 : ->set() déclenche updatedSiteName() automatiquement.
        Livewire::test(CheckoutPage::class)
            ->set('siteName', 'Lotissement Les Acacias');

        $this->assertSame('Lotissement Les Acacias', CartSession::current()->meta['pko_site_name']);
    }

    public function test_checkout_charge_site_name_depuis_cart_meta(): void
    {
        $this->makeCartWithMeta(['pko_site_name' => 'Chantier Existant']);

        Livewire::test(CheckoutPage::class)
            ->assertSet('siteName', 'Chantier Existant');
    }

    public function test_checkout_sanitise_le_html_dans_le_site_name(): void
    {
        $cart = $this->makeCartWithMeta([]);

        Livewire::test(CheckoutPage::class)
            ->set('siteName', '<script>alert(1)</script>Chantier');

        $stored = CartSession::current()->meta['pko_site_name'];
        $this->assertStringNotContainsString('<script>', (string) $stored);
        $this->assertStringContainsString('Chantier', (string) $stored);
    }

    // ── Account OrderDetailPage Livewire tests ────────────────────────────────

    public function test_client_peut_modifier_le_nom_de_chantier_de_sa_commande(): void
    {
        [$customer, $user] = $this->makeCustomerWithUser();
        $order = $this->makeOrderForCustomer($customer, 'payment-received', 'Ancien chantier');

        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['order' => $order])
            ->set('siteName', 'Nouveau chantier')
            ->call('saveSiteName')
            ->assertHasNoErrors()
            ->assertSet('editingSiteName', false);

        $this->assertSame('Nouveau chantier', $order->fresh()->pko_site_name);
    }

    public function test_client_peut_effacer_le_nom_de_chantier(): void
    {
        [$customer, $user] = $this->makeCustomerWithUser();
        $order = $this->makeOrderForCustomer($customer, 'payment-received', 'Chantier à effacer');

        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['order' => $order])
            ->set('siteName', '')
            ->call('saveSiteName')
            ->assertHasNoErrors();

        $this->assertNull($order->fresh()->pko_site_name);
    }

    public function test_client_ne_peut_pas_voir_la_commande_dun_autre_client(): void
    {
        // mount() re-vérifie customer_id → abort(404) pour tout utilisateur non propriétaire.
        [$customer1] = $this->makeCustomerWithUser();
        [, $user2] = $this->makeCustomerWithUser();

        $order = $this->makeOrderForCustomer($customer1, 'payment-received', 'Chantier A');

        $this->actingAs($user2);

        Livewire::test(OrderDetailPage::class, ['order' => $order])
            ->assertNotFound();

        $this->assertSame('Chantier A', $order->fresh()->pko_site_name);
    }

    public function test_site_name_trop_long_est_refuse(): void
    {
        [$customer, $user] = $this->makeCustomerWithUser();
        $order = $this->makeOrderForCustomer($customer);

        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['order' => $order])
            ->set('siteName', str_repeat('A', 300))
            ->call('saveSiteName')
            ->assertHasErrors('siteName');
    }

    public function test_annuler_restaure_la_valeur_initiale(): void
    {
        [$customer, $user] = $this->makeCustomerWithUser();
        $order = $this->makeOrderForCustomer($customer, 'payment-received', 'Chantier Original');

        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['order' => $order])
            ->set('siteName', 'Valeur modifiée non sauvegardée')
            ->call('cancelEditSiteName')
            ->assertSet('siteName', 'Chantier Original')
            ->assertSet('editingSiteName', false);
    }
}
