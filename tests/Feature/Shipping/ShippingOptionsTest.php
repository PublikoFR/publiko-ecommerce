<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Livewire\Components\ShippingOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Lunar\Base\ShippingManifestInterface;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingCommon\Modifiers\FrancoModifier;
use Pko\ShippingCommon\Support\WeightCalculator;
use Tests\TestCase;

class ShippingOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeOption(string $identifier, int $priceCents, bool $franco = false): ShippingOption
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $taxClass = TaxClass::make(['name' => 'Default', 'default' => true]);

        return new ShippingOption(
            name: $identifier,
            description: '',
            identifier: $identifier,
            price: new Price($priceCents, $currency, 1),
            taxClass: $taxClass,
            meta: $franco ? ['franco' => true] : [],
        );
    }

    private function bindManifestWith(array $options): void
    {
        $manifest = Mockery::mock(ShippingManifestInterface::class);
        $manifest->shouldReceive('getOptions')
            ->andReturn(new Collection($options));

        $this->app->instance(ShippingManifestInterface::class, $manifest);
    }

    private function makeCart(): Cart
    {
        $currency = Currency::factory()->create(['default' => true]);
        $cart = Cart::factory()->create(['currency_id' => $currency->id]);
        CartSession::use($cart);

        return $cart;
    }

    private function makeLine(bool $francoEligible, int $subtotalHtCents = 20000, ?int $supplierId = null): object
    {
        $product = (object) [
            'pko_franco_eligible' => $francoEligible,
            'pko_logistics_class' => 'A',
            'pko_quote_only' => false,
            'pko_supplier_id' => $supplierId,
            'pko_free_shipping' => false,
        ];

        $variant = (object) [
            'weight_value' => 1.0,
            'weight_unit' => 'kg',
            'product' => $product,
        ];

        return (object) [
            'purchasable' => $variant,
            'quantity' => 1,
            'subTotal' => (object) ['value' => $subtotalHtCents],
        ];
    }

    private function mockCart(array $lines): Cart&MockInterface
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')->with('lines')->andReturn(new Collection($lines));
        $cart->shouldReceive('getAttribute')->with('currency')->andReturn($currency);

        return $cart;
    }

    // ── Tests mount() ─────────────────────────────────────────────────────────

    public function test_chrono13_selectionne_par_defaut_quand_pas_doption_sauvegardee(): void
    {
        $this->makeCart();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
            $this->makeOption('chronopost.chrono13', 1890),
            $this->makeOption('chronopost.chrono10', 2490),
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSet('chosenOption', FrancoModifier::CHRONO13_IDENTIFIER);
    }

    public function test_premiere_option_si_chrono13_absent(): void
    {
        $this->makeCart();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
            $this->makeOption('chronopost.chrono10', 2490),
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSet('chosenOption', 'chronopost.chrono_relais');
    }

    // ── Tests bandeaux via WeightCalculator ──────────────────────────────────

    public function test_is_franco_reached_vrai_quand_seuil_atteint_sans_exclusion(): void
    {
        $cart = $this->mockCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 40000),
        ]);

        $this->assertTrue(
            WeightCalculator::francoEligibleSubtotalHt($cart) >= 35000
            && ! WeightCalculator::cartHasFrancoExcludedLine($cart),
            'Franco doit être atteint : 400 € HT de lignes éligibles sans exclusion'
        );
    }

    public function test_is_franco_reached_faux_quand_seuil_non_atteint(): void
    {
        $cart = $this->mockCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 20000),
        ]);

        $this->assertFalse(
            WeightCalculator::francoEligibleSubtotalHt($cart) >= 35000,
            'Franco ne doit pas être atteint avec seulement 200 € HT'
        );
    }

    public function test_has_excluded_lines_vrai_quand_une_ligne_est_exclue(): void
    {
        $cart = $this->mockCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 40000),
            $this->makeLine(francoEligible: false, subtotalHtCents: 5000),
        ]);

        $this->assertTrue(WeightCalculator::cartHasFrancoExcludedLine($cart));
    }
}
