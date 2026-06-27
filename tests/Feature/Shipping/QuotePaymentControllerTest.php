<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\Transaction;
use Lunar\Stripe\Facades\Stripe;
use Pko\ShippingCommon\Jobs\CreateCarrierShipmentJob;
use Tests\TestCase;

class QuotePaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // Dummy key so the Stripe SDK passes its auth guard; Stripe::fake() routes
        // every API call through the mock HTTP client (no real, billable call).
        config([
            'services.stripe.key' => 'sk_test_dummy',
            'services.stripe.public_key' => 'pk_test_dummy',
        ]);
        Stripe::fake();

        // Keep the post-payment carrier job out of the DB transaction / SOAP layer.
        Queue::fake();
    }

    // ── show ────────────────────────────────────────────────────────────────

    public function test_signed_link_renders_payment_page_with_total(): void
    {
        $order = $this->makeQuoteOrder(total: 10000);

        $url = URL::signedRoute('pko.quote.pay', [
            'order' => $order->id,
            'transport_cents' => 1490,
        ], now()->addDays(7));

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee(brand_name(), false);
        $response->assertSee('114,90', false);        // 100,00 + 14,90 € total
        $response->assertSee('payment-element', false);

        // The intent must be persisted on the order for the confirmation step.
        $this->assertNotNull($order->fresh()->meta['quote_payment']['intent_id'] ?? null);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->makeQuoteOrder();

        // Unsigned URL → the `signed` middleware aborts with 403.
        $this->get(route('pko.quote.pay', ['order' => $order->id, 'transport_cents' => 1490]))
            ->assertForbidden();
    }

    public function test_non_quote_order_returns_gone(): void
    {
        $order = $this->makeQuoteOrder(status: 'payment-received');

        $url = URL::signedRoute('pko.quote.pay', [
            'order' => $order->id,
            'transport_cents' => 1490,
        ], now()->addDays(7));

        $this->get($url)->assertStatus(410);
    }

    // ── confirm ──────────────────────────────────────────────────────────────

    public function test_successful_payment_marks_order_paid_and_creates_shipment(): void
    {
        $order = $this->makeQuoteOrder(total: 10000, withLine: true, shippingOption: 'chronopost.chrono13');
        $this->storeIntent($order, 'PI_CAPTURE', 1490);

        $response = $this->get(route('pko.quote.pay.confirm', ['order' => $order->id]));

        $response->assertOk();
        $response->assertSee('confirmé', false);

        $order->refresh();
        $this->assertSame('payment-received', $order->status);
        $this->assertSame(11490, (int) $order->total->value);   // 10000 + 1490 transport

        $this->assertDatabaseHas('lunar_transactions', [
            'order_id' => $order->id,
            'reference' => 'PI_CAPTURE',
            'driver' => 'stripe',
            'type' => 'capture',
            'success' => true,
        ]);

        // Post-payment observer dispatches the carrier shipment job.
        Queue::assertPushed(CreateCarrierShipmentJob::class);
    }

    public function test_confirm_is_idempotent(): void
    {
        $order = $this->makeQuoteOrder(total: 10000, withLine: true, shippingOption: 'chronopost.chrono13');
        $this->storeIntent($order, 'PI_CAPTURE', 1490);

        $this->get(route('pko.quote.pay.confirm', ['order' => $order->id]))->assertOk();
        $this->get(route('pko.quote.pay.confirm', ['order' => $order->id]))->assertOk();

        $this->assertSame(1, Transaction::query()->where('order_id', $order->id)->count());
        $this->assertSame(11490, (int) $order->fresh()->total->value);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeQuoteOrder(
        string $status = 'awaiting-quote',
        int $total = 10000,
        bool $withLine = false,
        ?string $shippingOption = null,
    ): Order {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        $id = \DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'status' => $status,
            'reference' => 'QUOTE-'.uniqid(),
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'exchange_rate' => 1,
            'sub_total' => $total,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => $total,
            'tax_breakdown' => '[]',
            'discount_breakdown' => '[]',
            'shipping_breakdown' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($shippingOption !== null) {
            \DB::table('lunar_order_addresses')->insert([
                'order_id' => $id,
                'type' => 'shipping',
                'first_name' => 'Jean',
                'last_name' => 'Test',
                'contact_email' => 'client@example.test',
                'shipping_option' => $shippingOption,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($withLine) {
            $variant = Product::query()->first()->variants()->first();

            \DB::table('lunar_order_lines')->insert([
                'order_id' => $id,
                'purchasable_type' => ProductVariant::class,
                'purchasable_id' => $variant->id,
                'type' => 'physical',
                'description' => 'Test',
                'identifier' => 'TEST-SKU',
                'unit_price' => $total,
                'unit_quantity' => 1,
                'quantity' => 1,
                'sub_total' => $total,
                'discount_total' => 0,
                'tax_breakdown' => '[]',
                'tax_total' => 0,
                'total' => $total,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return Order::findOrFail($id);
    }

    private function storeIntent(Order $order, string $intentId, int $transportCents): void
    {
        $order->meta = ['quote_payment' => [
            'intent_id' => $intentId,
            'transport_cents' => $transportCents,
            'amount' => (int) $order->total->value + $transportCents,
        ]];
        $order->save();
    }
}
