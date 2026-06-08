<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use Illuminate\Support\Collection;
use Lunar\Models\Cart;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingCommon\Support\WeightCalculator;
use Tests\TestCase;

class WeightCalculatorFreeShippingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeLine(float $weightKg, bool $freeShipping, int $qty = 1): object
    {
        $product = (object) ['pko_free_shipping' => $freeShipping];

        $variant = new class($weightKg, $product)
        {
            public string $weight_unit = 'kg';

            public function __construct(
                public float $weight_value,
                public object $product,
            ) {}
        };

        return new class($variant, $qty)
        {
            public function __construct(
                public mixed $purchasable,
                public int $quantity,
            ) {}
        };
    }

    /** @param  list<object>  $lines */
    private function makeCart(array $lines): Cart&MockInterface
    {
        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')
            ->with('lines')
            ->andReturn(new Collection($lines));

        return $cart;
    }

    // ── fromCartTaxable ───────────────────────────────────────────────────────

    public function test_taxable_weight_excludes_free_shipping_lines(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(2.0, false, 1),  // normal — 2 kg
            $this->makeLine(3.0, true, 1),   // free — excluded
        ]);

        $this->assertSame(2.0, WeightCalculator::fromCartTaxable($cart));
    }

    public function test_taxable_weight_is_zero_when_all_lines_free(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(1.5, true, 2),
            $this->makeLine(0.5, true, 1),
        ]);

        $this->assertSame(0.0, WeightCalculator::fromCartTaxable($cart));
    }

    public function test_taxable_weight_sums_only_normal_lines_in_mixed_cart(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(1.0, false, 3),  // 3 kg
            $this->makeLine(2.0, true, 1),   // free — excluded
            $this->makeLine(0.5, false, 2),  // 1 kg
        ]);

        $this->assertSame(4.0, WeightCalculator::fromCartTaxable($cart));
    }

    public function test_taxable_weight_equals_full_weight_when_no_free_shipping(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(1.0, false, 2),
            $this->makeLine(0.5, false, 1),
        ]);

        $this->assertSame(2.5, WeightCalculator::fromCartTaxable($cart));
    }

    // ── allLinesFreeShipping ──────────────────────────────────────────────────

    public function test_all_free_returns_true_when_all_lines_flagged(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(1.0, true),
            $this->makeLine(2.0, true),
        ]);

        $this->assertTrue(WeightCalculator::allLinesFreeShipping($cart));
    }

    public function test_all_free_returns_false_for_mixed_cart(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(1.0, true),
            $this->makeLine(2.0, false),
        ]);

        $this->assertFalse(WeightCalculator::allLinesFreeShipping($cart));
    }

    public function test_all_free_returns_false_when_no_lines_free(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(1.0, false),
        ]);

        $this->assertFalse(WeightCalculator::allLinesFreeShipping($cart));
    }

    public function test_all_free_returns_false_for_empty_cart(): void
    {
        $cart = $this->makeCart([]);

        $this->assertFalse(WeightCalculator::allLinesFreeShipping($cart));
    }
}
