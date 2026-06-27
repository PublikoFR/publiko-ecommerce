<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Livewire\CheckoutPage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Lunar\Base\DataTransferObjects\PaymentAuthorize;
use Lunar\Base\ShippingManifestInterface;
use Lunar\Facades\CartSession;
use Lunar\Facades\Payments;
use Lunar\Models\Cart;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Mockery;
use Tests\TestCase;

/**
 * Couvre la bifurcation du checkout storefront (F3) :
 * un panier contenant un produit pko_quote_only court-circuite le paiement
 * et crée la commande en statut « awaiting-quote » ; un panier normal suit
 * le flux de paiement habituel, inchangé.
 */
class CheckoutQuoteInterceptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a cart with a single line of the given product, plus a valid
     * billing address. The variant is made non-shippable so the order-creation
     * validator only requires a billing address (the shipping branch is covered
     * by the pipeline test). The bifurcation under test depends solely on
     * product.pko_quote_only.
     */
    private function makeCartWith(bool $quoteOnly): Cart
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $product->forceFill(['pko_quote_only' => $quoteOnly])->save();

        /** @var ProductVariant $variant */
        $variant = $product->variants()->first();
        $variant->forceFill(['shippable' => false])->save();

        $currency = Currency::query()->where('default', true)->first()
            ?? Currency::query()->first();

        $cart = Cart::factory()->create(['currency_id' => $currency->id]);
        CartSession::use($cart);

        $cart->add($variant, 1);

        $cart->setBillingAddress([
            'first_name' => 'Romain',
            'last_name' => 'Galvez',
            'line_one' => '54 Rue des Châtaigniers',
            'city' => 'Béziers',
            'postcode' => '34500',
            'country_id' => Country::query()->value('id'),
            'contact_email' => 'riderfx3@gmail.com',
        ]);

        return $cart->refresh();
    }

    /**
     * Bind a shipping manifest that exposes no options — the test variants are
     * non-shippable so the manifest is never consulted by the validator, but
     * mount() still queries it.
     */
    private function bindEmptyManifest(): void
    {
        $manifest = Mockery::mock(ShippingManifestInterface::class);
        $manifest->shouldReceive('getOptions')->andReturn(new Collection);
        $manifest->shouldReceive('getShippingOption')->andReturnNull();

        $this->app->instance(ShippingManifestInterface::class, $manifest);
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function test_quote_only_cart_creates_awaiting_quote_order_without_payment(): void
    {
        $this->bindEmptyManifest();
        $cart = $this->makeCartWith(quoteOnly: true);

        // Aucun paiement ne doit être déclenché.
        Payments::shouldReceive('cart')->never();

        Livewire::test(CheckoutPage::class)
            ->call('checkout')
            ->assertHasNoErrors()
            ->assertRedirect(route('checkout-success.view'));

        $order = Order::query()->where('cart_id', $cart->id)->first();

        $this->assertNotNull($order, 'Une commande doit être créée pour le panier sur devis.');
        $this->assertSame('awaiting-quote', $order->status);
        $this->assertNotNull($order->placed_at, 'La commande sur devis doit être passée (placed_at).');
        $this->assertCount(0, $order->transactions, 'Aucune transaction de paiement ne doit exister.');
    }

    public function test_normal_cart_follows_standard_payment_flow(): void
    {
        $this->bindEmptyManifest();
        $cart = $this->makeCartWith(quoteOnly: false);

        // Le flux normal doit passer par le manager de paiement.
        $driver = Mockery::mock();
        $driver->shouldReceive('withData')->andReturnSelf();
        $driver->shouldReceive('authorize')->andReturn(new PaymentAuthorize(success: true));

        Payments::shouldReceive('cart')->once()->andReturn($driver);

        Livewire::test(CheckoutPage::class)
            ->call('checkout')
            ->assertRedirect(route('checkout-success.view'));

        // La bifurcation « devis » ne doit pas s'être déclenchée :
        // aucune commande awaiting-quote n'est créée hors du flux de paiement mocké.
        $this->assertDatabaseMissing('lunar_orders', [
            'cart_id' => $cart->id,
            'status' => 'awaiting-quote',
        ]);
    }
}
