<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\ShippingCommon\Pipelines\MarkQuoteOrderAwaitingQuote;
use Tests\TestCase;

class QuoteOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Calls the pipeline step and returns the (possibly mutated) order.
     * The $next closure captures the order so we can verify it wasn't
     * further modified by subsequent pipeline steps.
     */
    private function runPipeline(Order $order): Order
    {
        $pipe = new MarkQuoteOrderAwaitingQuote;
        $passed = null;

        $pipe->handle($order, function (Order $o) use (&$passed): Order {
            $passed = $o;

            return $o;
        });

        return $passed ?? $order;
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function test_order_without_quote_only_lines_keeps_default_status(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product);

        // Ensure the seeded product does NOT have pko_quote_only set
        $product->forceFill(['pko_quote_only' => false])->save();

        // Use a minimal Order stub (no real order lines in DB — the pipeline
        // will load an empty collection and leave the status unchanged)
        $order = $this->makeMinimalOrder('awaiting-payment');
        $result = $this->runPipeline($order);

        $this->assertSame('awaiting-payment', $result->status);
    }

    public function test_pipeline_sets_awaiting_quote_when_quote_only_line_exists(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $this->assertNotNull($product);

        $product->forceFill(['pko_quote_only' => true])->save();

        // Attach a real order line pointing to a variant of this product
        $variant = $product->variants()->first();
        $this->assertNotNull($variant, 'Le produit seedé doit avoir au moins une variante.');

        $order = $this->makeMinimalOrder('awaiting-payment');

        // Create a minimal order line via DB to avoid full cart pipeline
        \DB::table('lunar_order_lines')->insert([
            'order_id' => $order->id,
            'purchasable_type' => ProductVariant::class,
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'description' => 'Test',
            'identifier' => 'TEST-SKU',
            'unit_price' => 1000,
            'unit_quantity' => 100,
            'quantity' => 1,
            'sub_total' => 1000,
            'discount_total' => 0,
            'tax_breakdown' => '[]',
            'tax_total' => 200,
            'total' => 1200,
            'notes' => null,
            'meta' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->runPipeline($order);

        $this->assertSame('awaiting-quote', $result->fresh()->status);
    }

    // ── private ──────────────────────────────────────────────────────────────

    private function makeMinimalOrder(string $status): Order
    {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        // Use DB::table to bypass Lunar's custom breakdown casts (TaxBreakdown,
        // DiscountBreakdown, ShippingBreakdown) which don't accept plain arrays.
        $id = \DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'status' => $status,
            'reference' => 'TEST-'.uniqid(),
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
}
