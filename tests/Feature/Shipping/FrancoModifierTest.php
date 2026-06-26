<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Support\Collection;
use Lunar\Base\ShippingManifestInterface;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingCommon\Modifiers\FrancoModifier;
use Tests\TestCase;

class FrancoModifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeLine(
        bool $francoEligible,
        string $logisticsClass = 'A',
        bool $quoteOnly = false,
        int $subtotalHtCents = 20000,
    ): object {
        $product = (object) [
            'pko_franco_eligible' => $francoEligible,
            'pko_logistics_class' => $logisticsClass,
            'pko_quote_only' => $quoteOnly,
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

    private function makeCart(array $lines): Cart&MockInterface
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);

        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')->with('lines')->andReturn(new Collection($lines));
        $cart->shouldReceive('getAttribute')->with('currency')->andReturn($currency);
        $cart->shouldReceive('offsetExists')->andReturnUsing(
            fn ($key): bool => in_array($key, ['lines', 'currency'], true),
        );

        return $cart;
    }

    private function makePaidShippingOption(string $identifier, int $priceCents = 990): ShippingOption
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $taxClass = TaxClass::make(['id' => 1, 'name' => 'Default', 'default' => true]);

        return new ShippingOption(
            name: 'Service '.$identifier,
            description: 'Description '.$identifier,
            identifier: $identifier,
            price: new Price($priceCents, $currency, 1),
            taxClass: $taxClass,
            meta: ['carrier' => 'chronopost', 'service_code' => explode('.', $identifier)[1] ?? $identifier],
        );
    }

    /**
     * Pre-populate manifest with carrier options and run the modifier.
     */
    private function runModifier(Cart&MockInterface $cart, array $preloadOptions = []): Collection
    {
        ShippingManifest::clearOptions();

        foreach ($preloadOptions as $option) {
            ShippingManifest::addOption($option);
        }

        $modifier = new FrancoModifier;
        $modifier->handle($cart, fn ($c) => $c);

        return app(ShippingManifestInterface::class)->options;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_chrono13_devient_gratuit_quand_seuil_atteint_et_panier_eligible(): void
    {
        // 2 lignes éligibles, sous-total HT total = 40 000 cents (400 €) >= 35 000
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 20000),
            $this->makeLine(francoEligible: true, subtotalHtCents: 20000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidShippingOption('chronopost.chrono13', 990),
            $this->makePaidShippingOption('chronopost.chrono_relais', 690),
            $this->makePaidShippingOption('chronopost.chrono10', 1490),
        ]);

        $chrono13 = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertNotNull($chrono13, 'chrono13 doit rester dans le manifest');
        $this->assertSame(0, $chrono13->price->value, 'chrono13 doit être à 0 €');
        $this->assertSame('Livraison standard offerte', $chrono13->name);
        $this->assertTrue($chrono13->meta['franco'] ?? false, 'meta franco=true attendu');

        // Relais et Chrono 10 restent payants
        $relais = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === 'chronopost.chrono_relais');
        $this->assertNotNull($relais);
        $this->assertSame(690, $relais->price->value, 'chrono_relais doit rester payant');

        $chrono10 = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === 'chronopost.chrono10');
        $this->assertNotNull($chrono10);
        $this->assertSame(1490, $chrono10->price->value, 'chrono10 doit rester payant');
    }

    public function test_aucune_modification_quand_seuil_non_atteint(): void
    {
        // Sous-total HT = 20 000 cents (200 €) < 35 000
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 20000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidShippingOption('chronopost.chrono13', 990),
        ]);

        $chrono13 = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertNotNull($chrono13);
        $this->assertSame(990, $chrono13->price->value, 'chrono13 doit garder son prix grille sous le seuil');
    }

    public function test_franco_non_applique_si_une_ligne_exclue(): void
    {
        // Sous-total HT éligible = 40 000 (>= 35 000) mais 1 ligne non éligible
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 40000),
            $this->makeLine(francoEligible: false, subtotalHtCents: 5000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidShippingOption('chronopost.chrono13', 990),
        ]);

        $chrono13 = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertNotNull($chrono13);
        $this->assertSame(990, $chrono13->price->value, 'chrono13 doit garder son prix grille si panier mixte');
    }

    public function test_franco_non_applique_si_ligne_classe_c(): void
    {
        // Classe logistique C → exclue du franco même si pko_franco_eligible=true
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, logisticsClass: 'A', subtotalHtCents: 30000),
            $this->makeLine(francoEligible: true, logisticsClass: 'C', subtotalHtCents: 15000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidShippingOption('chronopost.chrono13', 990),
        ]);

        $chrono13 = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertSame(990, $chrono13->price->value, 'classe C doit bloquer le franco');
    }

    public function test_franco_non_applique_si_ligne_quote_only(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 30000),
            $this->makeLine(francoEligible: true, quoteOnly: true, subtotalHtCents: 15000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidShippingOption('chronopost.chrono13', 990),
        ]);

        $chrono13 = $options->first(fn (ShippingOption $o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertSame(990, $chrono13->price->value, 'quote_only doit bloquer le franco');
    }

    public function test_pas_de_doublon_didentifier_apres_remplacement(): void
    {
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 40000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidShippingOption('chronopost.chrono13', 990),
        ]);

        $chrono13Count = $options->filter(
            fn (ShippingOption $o) => $o->getIdentifier() === 'chronopost.chrono13'
        )->count();

        $this->assertSame(1, $chrono13Count, "L'identifier chronopost.chrono13 ne doit apparaître qu'une fois");
    }

    public function test_aucune_action_si_chrono13_absent_du_manifest(): void
    {
        // Le modifier ne doit pas planter si chrono13 n'est pas dans le manifest
        $cart = $this->makeCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 40000),
        ]);

        $options = $this->runModifier($cart, [
            $this->makePaidShippingOption('chronopost.chrono_relais', 690),
        ]);

        $this->assertCount(1, $options);
        $this->assertSame(690, $options->first()->price->value);
    }
}
