<?php

declare(strict_types=1);

namespace Tests\Feature\Ecotax;

use App\Support\Ecotax;
use Illuminate\Database\Eloquent\Collection;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

/**
 * Contrat du helper App\Support\Ecotax (modèles non persistés : aucun accès DB).
 */
class EcotaxHelperTest extends TestCase
{
    private function variant(?int $ecotax): ProductVariant
    {
        $variant = new ProductVariant;
        $variant->pko_ecotax_cents = $ecotax;

        return $variant;
    }

    private function cartLine(mixed $purchasable, int $quantity): CartLine
    {
        $line = (new CartLine)->forceFill(['quantity' => $quantity]);
        $line->setRelation('purchasable', $purchasable);

        return $line;
    }

    /** @param  array<string, mixed>|null  $meta */
    private function orderLine(?array $meta, int $quantity, string $type = 'physical', ?ProductVariant $variant = null): OrderLine
    {
        $line = (new OrderLine)->forceFill(['meta' => $meta, 'quantity' => $quantity, 'type' => $type]);
        if ($variant !== null) {
            $line->setRelation('purchasable', $variant);
        }

        return $line;
    }

    public function test_for_purchasable(): void
    {
        $this->assertSame(16, Ecotax::forPurchasable($this->variant(16)));
        $this->assertSame(0, Ecotax::forPurchasable($this->variant(null)));
        $this->assertSame(0, Ecotax::forPurchasable(null));
        $this->assertSame(0, Ecotax::forPurchasable(new \stdClass));
    }

    public function test_cart_line_and_cart_totals_use_current_variant(): void
    {
        $a = $this->cartLine($this->variant(16), 3);
        $b = $this->cartLine($this->variant(null), 5);
        $c = $this->cartLine($this->variant(19), 1);

        $this->assertSame(48, Ecotax::forCartLine($a));
        $this->assertSame(0, Ecotax::forCartLine($b));

        $cart = new Cart;
        $cart->setRelation('lines', new Collection([$a, $b, $c]));

        $this->assertSame(67, Ecotax::forCart($cart));
    }

    public function test_order_line_reads_only_frozen_meta_never_the_variant(): void
    {
        // La variante a changé depuis la commande (99) : seule la meta figée compte.
        $frozen = $this->orderLine(['ecotax_cents' => 16, 'other' => 'x'], 2, variant: $this->variant(99));
        $notFrozen = $this->orderLine(null, 4, variant: $this->variant(99));

        $this->assertSame(16, Ecotax::unitForOrderLine($frozen));
        $this->assertSame(32, Ecotax::forOrderLine($frozen));
        $this->assertSame(0, Ecotax::unitForOrderLine($notFrozen));
        $this->assertSame(0, Ecotax::forOrderLine($notFrozen));
    }

    public function test_order_total_ignores_shipping_lines(): void
    {
        $order = new Order;
        $order->setRelation('lines', new Collection([
            $this->orderLine(['ecotax_cents' => 16], 2),
            $this->orderLine(['ecotax_cents' => 19], 1),
            $this->orderLine(['ecotax_cents' => 500], 1, 'shipping'),
        ]));

        $this->assertSame(51, Ecotax::forOrder($order));
    }
}
