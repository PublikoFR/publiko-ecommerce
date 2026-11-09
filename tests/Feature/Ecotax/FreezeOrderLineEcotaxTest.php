<?php

declare(strict_types=1);

namespace Tests\Feature\Ecotax;

use App\Pipelines\Orders\FreezeOrderLineEcotax;
use App\Support\Ecotax;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pipeline\Pipeline;
use Lunar\DataTypes\ShippingOption;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use Lunar\Pipelines\Order\Creation\CleanUpOrderLines;
use Lunar\Pipelines\Order\Creation\CreateOrderLines;
use Pko\ShippingCommon\Pipelines\MarkQuoteOrderAwaitingQuote;
use Tests\TestCase;

class FreezeOrderLineEcotaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function variantWithEcotax(?int $ecotax): ProductVariant
    {
        $variant = ProductVariant::factory()->create();
        $variant->pko_ecotax_cents = $ecotax;
        $variant->save();

        return $variant;
    }

    private function line(Order $order, ProductVariant $variant, ?array $meta, int $quantity = 1): OrderLine
    {
        return OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'quantity' => $quantity,
            'meta' => $meta,
        ]);
    }

    private function freeze(Order $order): void
    {
        (new FreezeOrderLineEcotax)->handle($order, fn (Order $o) => $o);
    }

    public function test_freezes_unit_ecotax_and_keeps_existing_meta(): void
    {
        $order = Order::withoutEvents(fn () => Order::factory()->create());
        $withEcotax = $this->line($order, $this->variantWithEcotax(16), ['color' => 'blanc'], 3);
        $withoutEcotax = $this->line($order, $this->variantWithEcotax(null), ['color' => 'noir']);
        $zeroEcotax = $this->line($order, $this->variantWithEcotax(0), null);

        $this->freeze($order);

        $withEcotax->refresh();
        $this->assertSame(16, $withEcotax->meta['ecotax_cents']);
        $this->assertSame('blanc', $withEcotax->meta['color']);
        $this->assertSame(48, Ecotax::forOrderLine($withEcotax));

        $withoutEcotax->refresh();
        $this->assertSame(['color' => 'noir'], $withoutEcotax->meta->getArrayCopy());
        $this->assertNull($zeroEcotax->refresh()->meta['ecotax_cents'] ?? null);
    }

    public function test_frozen_value_survives_a_later_variant_change(): void
    {
        $order = Order::withoutEvents(fn () => Order::factory()->create());
        $variant = $this->variantWithEcotax(16);
        $line = $this->line($order, $variant, null, 2);

        $this->freeze($order);

        $variant->pko_ecotax_cents = 99;
        $variant->save();

        $this->assertSame(16, Ecotax::unitForOrderLine($line->refresh()));
        $this->assertSame(32, Ecotax::forOrder($order->refresh()));
    }

    public function test_shipping_lines_are_ignored(): void
    {
        $order = Order::withoutEvents(fn () => Order::factory()->create());
        $shipping = OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => ShippingOption::class,
            'purchasable_id' => 0,
            'type' => 'shipping',
            'meta' => null,
        ]);

        $this->freeze($order);

        $this->assertNull($shipping->refresh()->meta);
    }

    public function test_line_survives_cleanup_when_frozen_after_it(): void
    {
        $variant = $this->variantWithEcotax(16);
        $cart = Cart::factory()->create();
        CartLine::factory()->create([
            'cart_id' => $cart->id,
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'quantity' => 2,
            'meta' => null,
        ]);
        $order = Order::withoutEvents(fn () => Order::factory()->create(['cart_id' => $cart->id]));
        $line = $this->line($order, $variant, null, 2);

        // Ordre de config/lunar/orders.php : CleanUpOrderLines compare les meta au
        // panier, puis le figeage ajoute meta.ecotax_cents.
        app(Pipeline::class)
            ->send($order)
            ->through([CleanUpOrderLines::class, FreezeOrderLineEcotax::class])
            ->thenReturn();

        $this->assertNotNull($line->fresh(), 'La ligne ne doit pas être supprimée par CleanUpOrderLines.');
        $this->assertSame(16, Ecotax::unitForOrderLine($line->fresh()));
    }

    public function test_pipe_is_registered_after_order_line_cleanup_and_mark_quote_stays_last(): void
    {
        $pipes = config('lunar.orders.pipelines.creation');
        $freeze = array_search(FreezeOrderLineEcotax::class, $pipes, true);

        $this->assertIsInt($freeze);
        $this->assertGreaterThan(array_search(CreateOrderLines::class, $pipes, true), $freeze);
        $this->assertGreaterThan(array_search(CleanUpOrderLines::class, $pipes, true), $freeze);
        $this->assertSame(MarkQuoteOrderAwaitingQuote::class, end($pipes));
    }
}
