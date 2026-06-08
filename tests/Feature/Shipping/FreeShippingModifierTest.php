<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Support\Collection;
use Lunar\Base\ShippingManifestInterface;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingCommon\Modifiers\FreeShippingModifier;
use Tests\TestCase;

class FreeShippingModifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeLine(bool $freeShipping): object
    {
        $product = (object) ['pko_free_shipping' => $freeShipping];

        $variant = (object) [
            'weight_value' => 1.0,
            'weight_unit' => 'kg',
            'product' => $product,
        ];

        return (object) ['purchasable' => $variant, 'quantity' => 1];
    }

    private function makeCart(array $lines): Cart&MockInterface
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);

        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')->with('lines')->andReturn(new Collection($lines));
        $cart->shouldReceive('getAttribute')->with('currency')->andReturn($currency);

        return $cart;
    }

    /**
     * Run the modifier directly (bypasses the full pipeline) and return
     * the options that were added to the manifest.
     */
    private function runModifier(Cart&MockInterface $cart): Collection
    {
        ShippingManifest::clearOptions();

        $modifier = new FreeShippingModifier;
        $modifier->handle($cart, fn ($c) => $c);

        // Access the manifest's options property directly — avoids re-running
        // the full pipeline that getOptions() would trigger.
        return app(ShippingManifestInterface::class)->options;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_free_option_added_when_all_lines_flagged(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(true),
            $this->makeLine(true),
        ]);

        $options = $this->runModifier($cart);

        $this->assertTrue($options->isNotEmpty(), 'Expected at least one shipping option');

        $free = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === FreeShippingModifier::IDENTIFIER);
        $this->assertNotNull($free, 'free_shipping option should be present');
        $this->assertSame(0, $free->price->value);
    }

    public function test_no_free_option_when_cart_is_mixed(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(true),
            $this->makeLine(false),
        ]);

        $options = $this->runModifier($cart);

        $free = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === FreeShippingModifier::IDENTIFIER);
        $this->assertNull($free, 'free_shipping option must NOT appear for a mixed cart');
    }

    public function test_no_free_option_when_no_lines_flagged(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(false),
        ]);

        $options = $this->runModifier($cart);

        $free = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === FreeShippingModifier::IDENTIFIER);
        $this->assertNull($free);
    }

    public function test_no_free_option_for_empty_cart(): void
    {
        $cart = $this->makeCart([]);

        $options = $this->runModifier($cart);

        $free = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === FreeShippingModifier::IDENTIFIER);
        $this->assertNull($free, 'Empty cart should not trigger free shipping option');
    }
}
