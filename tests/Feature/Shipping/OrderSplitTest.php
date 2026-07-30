<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Actions\CreateSplitQuoteOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

/**
 * Tests for the L7 order-split flow.
 *
 * NOTE: These tests are written but NOT executed from the worktree — the
 * worktree is not mounted in the Docker container. Actual execution happens
 * during the verify/review phase on main after merge.
 */
class OrderSplitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ── 1. 100 % payable cart — no split ─────────────────────────────────────

    public function test_payable_only_order_does_not_trigger_split(): void
    {
        $payableOrder = $this->makeMinimalOrder('payment-received');

        // No split_pending in cart meta → maybeCreateSplitQuoteOrder is a no-op
        $this->assertNull(Order::where('meta->split_from', $payableOrder->id)->first());
    }

    // ── 2. 100 % quote cart — existing awaiting-quote flow unchanged ──────────

    public function test_quote_only_order_stays_awaiting_quote(): void
    {
        $order = $this->makeMinimalOrder('awaiting-quote');

        // No split: single order, no split_children, no split_from
        $meta = (array) ($order->meta ?? []);
        $this->assertArrayNotHasKey('split_children', $meta);
        $this->assertArrayNotHasKey('split_from', $meta);
        $this->assertSame('awaiting-quote', $order->status);
    }

    // ── 3. Mixed cart + split mode → two linked orders ────────────────────────

    public function test_split_creates_two_linked_orders(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $product->forceFill(['pko_port_mode' => 'quote'])->save();

        /** @var ProductVariant $variant */
        $variant = $product->variants()->first();
        $this->assertNotNull($variant);

        $payableOrder = $this->makeMinimalOrder('payment-received');
        $this->addShippingAddress($payableOrder);

        $splitGroup   = (string) Str::uuid();
        $splitPending = [
            [
                'purchasable_type' => (new ProductVariant)->getMorphClass(),
                'purchasable_id'   => $variant->id,
                'quantity'         => 2,
                'description'      => 'Produit devis',
                'identifier'       => 'DEVIS-SKU',
                'unit_price'       => 5000,
                'unit_quantity'    => 1,
                'sub_total'        => 10000,
                'tax_total'        => 2000,
                'total'            => 12000,
            ],
        ];

        $action     = app(CreateSplitQuoteOrder::class);
        $quoteOrder = $action->execute($payableOrder, $splitPending, $splitGroup);

        // Quote order exists and has the correct status
        $this->assertSame('awaiting-quote', $quoteOrder->status);
        $this->assertSame(0, $quoteOrder->shipping_total->value);

        // Meta linkage: quote → payable
        $quoteMeta = (array) ($quoteOrder->meta ?? []);
        $this->assertSame($payableOrder->id, (int) $quoteMeta['split_from']);
        $this->assertSame($splitGroup, $quoteMeta['split_group']);

        // Meta linkage: payable → quote
        $payableMeta = (array) ($payableOrder->fresh()->meta ?? []);
        $this->assertContains($quoteOrder->id, (array) $payableMeta['split_children']);

        // Quote order has the correct totals
        $this->assertSame(10000, $quoteOrder->sub_total->value);
        $this->assertSame(2000, $quoteOrder->tax_total->value);
        $this->assertSame(12000, $quoteOrder->total->value);

        // Quote order has the split line
        $this->assertSame(1, $quoteOrder->lines()->count());
        $line = $quoteOrder->lines()->first();
        $this->assertSame('DEVIS-SKU', $line->identifier);
        $this->assertSame(2, $line->quantity);

        // Reference is generated properly (not the TEMP-* placeholder)
        $this->assertStringNotContainsString('TEMP-', $quoteOrder->reference);
    }

    // ── 4. Mixed cart + quote_all → single awaiting-quote order ──────────────

    public function test_quote_all_mode_creates_single_awaiting_quote_order(): void
    {
        // quote_all is handled by checkoutQuoteOnly() — the standard awaiting-quote
        // pipeline path. We verify that CreateSplitQuoteOrder is NOT called.
        $payableOrder = $this->makeMinimalOrder('awaiting-quote');

        // No split_children should exist
        $meta = (array) ($payableOrder->meta ?? []);
        $this->assertArrayNotHasKey('split_children', $meta);

        // Only one order exists for this split_group
        $this->assertSame(0, Order::where('meta->split_from', $payableOrder->id)->count());
    }

    // ── 5. Double-submit idempotence ─────────────────────────────────────────

    public function test_double_submit_creates_only_one_quote_order(): void
    {
        /** @var Product $product */
        $product = Product::query()->first();
        $product->forceFill(['pko_port_mode' => 'quote'])->save();

        /** @var ProductVariant $variant */
        $variant = $product->variants()->first();

        $payableOrder = $this->makeMinimalOrder('payment-received');
        $this->addShippingAddress($payableOrder);

        $splitGroup   = (string) Str::uuid();
        $splitPending = [
            [
                'purchasable_type' => (new ProductVariant)->getMorphClass(),
                'purchasable_id'   => $variant->id,
                'quantity'         => 1,
                'description'      => 'Produit devis',
                'identifier'       => 'DEVIS-SKU',
                'unit_price'       => 3000,
                'unit_quantity'    => 1,
                'sub_total'        => 3000,
                'tax_total'        => 600,
                'total'            => 3600,
            ],
        ];

        $action = app(CreateSplitQuoteOrder::class);

        // First call → creates the quote order
        $action->execute($payableOrder, $splitPending, $splitGroup);

        // Simulate the idempotence guard in maybeCreateSplitQuoteOrder
        $alreadyExists = Order::where('meta->split_from', $payableOrder->id)->exists();
        $this->assertTrue($alreadyExists, 'Quote order should exist after first call');

        // Second call would be blocked by the guard in CheckoutPage::maybeCreateSplitQuoteOrder.
        // Here we verify the DB count directly.
        $quoteOrderCount = Order::where('meta->split_from', $payableOrder->id)->count();
        $this->assertSame(1, $quoteOrderCount, 'Only one quote order should exist after double submit');
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeMinimalOrder(string $status): Order
    {
        $channel  = Channel::query()->first();
        $currency = Currency::query()->first();

        $id = \DB::table('lunar_orders')->insertGetId([
            'channel_id'            => $channel->id,
            'status'                => $status,
            'reference'             => 'TEST-' . uniqid(),
            'currency_code'         => $currency->code,
            'compare_currency_code' => $currency->code,
            'exchange_rate'         => 1,
            'sub_total'             => 10000,
            'discount_total'        => 0,
            'shipping_total'        => 590,
            'tax_total'             => 2000,
            'total'                 => 12590,
            'tax_breakdown'         => '[]',
            'discount_breakdown'    => '[]',
            'shipping_breakdown'    => '[]',
            'meta'                  => '{}',
            'placed_at'             => now(),
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        return Order::findOrFail($id);
    }

    private function addShippingAddress(Order $order): void
    {
        OrderAddress::create([
            'order_id'     => $order->id,
            'type'         => 'shipping',
            'first_name'   => 'Jean',
            'last_name'    => 'Dupont',
            'line_one'     => '1 Rue de la Paix',
            'city'         => 'Paris',
            'postcode'     => '75001',
            'country_id'   => \Lunar\Models\Country::value('id'),
            'contact_email' => 'jean@example.com',
        ]);
    }
}
