<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\PkoShippingSurchargesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
use Pko\ShippingCommon\Models\ShippingSurcharge;
use Pko\ShippingCommon\Modifiers\SurchargeModifier;
use Pko\ShippingCommon\Support\ZoneResolver;
use Tests\TestCase;

class SurchargeModifierTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeCart(string $postcode, string $countryIso = 'FR'): Cart&MockInterface
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);

        $country = (object) ['iso2' => $countryIso];
        $address = (object) ['postcode' => $postcode, 'country' => $country];

        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')->with('shippingAddress')->andReturn($address);
        $cart->shouldReceive('getAttribute')->with('currency')->andReturn($currency);
        $cart->shouldReceive('offsetExists')->andReturnUsing(
            fn ($key): bool => in_array($key, ['shippingAddress', 'currency'], true),
        );

        return $cart;
    }

    private function makeCarrierOption(string $identifier, int $priceCents = 1890): ShippingOption
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $taxClass = TaxClass::make(['id' => 1, 'name' => 'Default', 'default' => true]);

        return new ShippingOption(
            name: 'Service '.$identifier,
            description: 'Description '.$identifier,
            identifier: $identifier,
            price: new Price($priceCents, $currency, 1),
            taxClass: $taxClass,
            meta: ['carrier' => 'chronopost'],
        );
    }

    private function runModifier(Cart&MockInterface $cart, array $preloadOptions = []): Collection
    {
        ShippingManifest::clearOptions();

        foreach ($preloadOptions as $option) {
            ShippingManifest::addOption($option);
        }

        $modifier = new SurchargeModifier;
        $modifier->handle($cart, fn ($c) => $c);

        return app(ShippingManifestInterface::class)->options;
    }

    private function corseSurcharge(bool $enabled = true, int $amountCents = 800): ShippingSurcharge
    {
        return ShippingSurcharge::create([
            'code' => 'corse',
            'label' => 'Supplément Corse',
            'amount_cents' => $amountCents,
            'mode' => 'auto',
            'rule' => ['type' => 'corse'],
            'enabled' => $enabled,
        ]);
    }

    // ── Tests auto mode ────────────────────────────────────────────────────

    public function test_supplement_corse_auto_ajoute_pour_cp_20xxx(): void
    {
        $this->corseSurcharge(enabled: true, amountCents: 800);

        $cart = $this->makeCart('20200');
        $options = $this->runModifier($cart, [
            $this->makeCarrierOption('chronopost.chrono13', 1890),
        ]);

        $option = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertNotNull($option);
        $this->assertSame(1890 + 800, $option->price->value, 'Le supplément doit être ajouté au prix carrier');
        $this->assertSame('corse', $option->meta['surcharge_code'] ?? null);
    }

    public function test_supplement_absent_pour_cp_metropole(): void
    {
        $this->corseSurcharge(enabled: true, amountCents: 800);

        $cart = $this->makeCart('75001');
        $options = $this->runModifier($cart, [
            $this->makeCarrierOption('chronopost.chrono13', 1890),
        ]);

        $option = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertNotNull($option);
        $this->assertSame(1890, $option->price->value, 'Aucun supplément pour la métropole');
    }

    public function test_surcharge_disabled_ignoree(): void
    {
        $this->corseSurcharge(enabled: false, amountCents: 800);

        $cart = $this->makeCart('20200');
        $options = $this->runModifier($cart, [
            $this->makeCarrierOption('chronopost.chrono13', 1890),
        ]);

        $option = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertNotNull($option);
        $this->assertSame(1890, $option->price->value, 'Surcharge désactivée = pas de majoration');
    }

    public function test_supplement_applique_sur_toutes_les_options_carrier(): void
    {
        $this->corseSurcharge(enabled: true, amountCents: 500);

        $cart = $this->makeCart('20200');
        $options = $this->runModifier($cart, [
            $this->makeCarrierOption('chronopost.chrono13', 1890),
            $this->makeCarrierOption('chronopost.chrono_relais', 1490),
        ]);

        $this->assertSame(1890 + 500, $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13')->price->value);
        $this->assertSame(1490 + 500, $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono_relais')->price->value);
    }

    public function test_supplement_par_postcode_prefix_rule(): void
    {
        ShippingSurcharge::create([
            'code' => 'corse_prefix',
            'label' => 'Supplément Corse (prefix)',
            'amount_cents' => 600,
            'mode' => 'auto',
            'rule' => ['postcode_prefix' => '20'],
            'enabled' => true,
        ]);

        $cart = $this->makeCart('20137');
        $options = $this->runModifier($cart, [
            $this->makeCarrierOption('chronopost.chrono13', 2000),
        ]);

        $option = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertSame(2000 + 600, $option->price->value);
    }

    // ── Tests quote mode ───────────────────────────────────────────────────

    public function test_mode_quote_injecte_option_sur_devis(): void
    {
        // TaxClass requise pour l'option quote (TaxClass::getDefault())
        TaxClass::create(['name' => 'Default', 'default' => true]);

        ShippingSurcharge::create([
            'code' => 'transport_specifique',
            'label' => 'Transport sur devis',
            'amount_cents' => 0,
            'mode' => 'quote',
            'rule' => ['type' => 'corse'],
            'enabled' => true,
        ]);

        $cart = $this->makeCart('20200');
        $options = $this->runModifier($cart);

        $quoteOption = $options->first(fn ($o) => $o->getIdentifier() === 'surcharge.transport_specifique');
        $this->assertNotNull($quoteOption, 'Option sur-devis absente du manifest');
        $this->assertTrue($quoteOption->meta['quote'] ?? false, 'meta.quote doit être true');
        $this->assertSame(0, $quoteOption->price->value, 'Prix sentinel = 0');
    }

    public function test_mode_quote_absent_hors_zone(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        ShippingSurcharge::create([
            'code' => 'transport_specifique',
            'label' => 'Transport sur devis',
            'amount_cents' => 0,
            'mode' => 'quote',
            'rule' => ['type' => 'corse'],
            'enabled' => true,
        ]);

        $cart = $this->makeCart('69001'); // Lyon, métropole
        $options = $this->runModifier($cart);

        $this->assertNull($options->first(fn ($o) => $o->getIdentifier() === 'surcharge.transport_specifique'));
    }

    public function test_mode_quote_ne_modifie_pas_les_options_existantes(): void
    {
        TaxClass::create(['name' => 'Default', 'default' => true]);

        ShippingSurcharge::create([
            'code' => 'transport_specifique',
            'label' => 'Transport sur devis',
            'amount_cents' => 0,
            'mode' => 'quote',
            'rule' => ['type' => 'corse'],
            'enabled' => true,
        ]);

        $cart = $this->makeCart('20200');
        $options = $this->runModifier($cart, [
            $this->makeCarrierOption('chronopost.chrono13', 1890),
        ]);

        $carrier = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertNotNull($carrier);
        $this->assertSame(1890, $carrier->price->value, 'Mode quote ne doit pas majorer les options carrier');
    }

    // ── Test option quote ignorée par auto ─────────────────────────────────

    public function test_auto_ne_majore_pas_les_options_quote(): void
    {
        $this->corseSurcharge(enabled: true, amountCents: 500);

        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $taxClass = TaxClass::make(['id' => 1, 'name' => 'Default', 'default' => true]);

        $quoteOption = new ShippingOption(
            name: 'Transport sur devis',
            description: 'Sur devis',
            identifier: 'surcharge.transport_specifique',
            price: new Price(0, $currency, 1),
            taxClass: $taxClass,
            meta: ['quote' => true, 'surcharge_code' => 'transport_specifique'],
        );

        $cart = $this->makeCart('20200');
        $options = $this->runModifier($cart, [$quoteOption]);

        $sentinel = $options->first(fn ($o) => $o->getIdentifier() === 'surcharge.transport_specifique');
        $this->assertNotNull($sentinel);
        $this->assertSame(0, $sentinel->price->value, "L'option quote ne doit pas être majorée");
    }

    // ── Test ZoneResolver ──────────────────────────────────────────────────

    public function test_zone_resolver_is_corse(): void
    {
        $this->assertTrue(ZoneResolver::isCorse('20200'));
        $this->assertTrue(ZoneResolver::isCorse('20000'));
        $this->assertTrue(ZoneResolver::isCorse('20137'));
        $this->assertFalse(ZoneResolver::isCorse('75001'));
        $this->assertFalse(ZoneResolver::isCorse('69001'));
        $this->assertFalse(ZoneResolver::isCorse('97100', 'GP'));
        $this->assertFalse(ZoneResolver::isCorse('20200', 'IT'));
    }

    public function test_is_metropole_exclut_toujours_la_corse(): void
    {
        $this->assertFalse(ZoneResolver::isMetropole('20200'));
        $this->assertFalse(ZoneResolver::isMetropole('20000'));
        $this->assertTrue(ZoneResolver::isMetropole('75001'));
    }

    // ── Tests d'intégration : seed réel des 9 suppléments ────────────────────

    public function test_seeder_seme_les_neuf_supplements_de_reference(): void
    {
        $this->seed(PkoShippingSurchargesSeeder::class);

        $expected = [
            'corse', 'zone_difficile', 'livraison_samedi',
            'hors_normes', 'manutention', 'transport_specifique',
            'assurance', 'correction_adresse', 'retour_expediteur',
        ];

        $this->assertSame(9, ShippingSurcharge::query()->count());
        foreach ($expected as $code) {
            $this->assertDatabaseHas('pko_shipping_surcharges', ['code' => $code]);
        }

        // Modes par défaut cohérents (cf. CR §5).
        $this->assertSame('auto', ShippingSurcharge::query()->where('code', 'corse')->value('mode'));
        $this->assertSame('quote', ShippingSurcharge::query()->where('code', 'transport_specifique')->value('mode'));
        foreach (['assurance', 'correction_adresse', 'retour_expediteur'] as $code) {
            $this->assertSame('rebill', ShippingSurcharge::query()->where('code', $code)->value('mode'));
        }

        // La règle 'corse' doit être exploitable par le modifier (type=corse).
        $this->assertSame(['type' => 'corse'], ShippingSurcharge::query()->where('code', 'corse')->value('rule'));
    }

    public function test_seeder_est_idempotent(): void
    {
        $this->seed(PkoShippingSurchargesSeeder::class);
        $this->seed(PkoShippingSurchargesSeeder::class);

        $this->assertSame(9, ShippingSurcharge::query()->count());
    }

    public function test_integration_supplement_corse_seede_applique_pour_cp_20xxx(): void
    {
        $this->seed(PkoShippingSurchargesSeeder::class);

        $cart = $this->makeCart('20200');
        $options = $this->runModifier($cart, [
            $this->makeCarrierOption('chronopost.chrono13', 1890),
        ]);

        $option = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertNotNull($option);
        $this->assertSame(1890 + 800, $option->price->value, 'Le supplément Corse seedé (800) doit majorer le prix carrier');
        $this->assertSame('corse', $option->meta['surcharge_code'] ?? null);
    }

    public function test_integration_aucun_supplement_seede_pour_cp_metropole(): void
    {
        $this->seed(PkoShippingSurchargesSeeder::class);

        $cart = $this->makeCart('75001');
        $options = $this->runModifier($cart, [
            $this->makeCarrierOption('chronopost.chrono13', 1890),
        ]);

        $option = $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13');
        $this->assertNotNull($option);
        $this->assertSame(1890, $option->price->value, 'Aucun supplément auto enabled ne matche la métropole');
    }

    public function test_integration_supplements_rebill_ignores_au_checkout(): void
    {
        $this->seed(PkoShippingSurchargesSeeder::class);

        // assurance/correction_adresse/retour_expediteur sont enabled mais en mode rebill :
        // ils ne doivent jamais injecter d'option ni majorer le prix au checkout.
        $cart = $this->makeCart('75001');
        $options = $this->runModifier($cart, [
            $this->makeCarrierOption('chronopost.chrono13', 1890),
        ]);

        foreach (['assurance', 'correction_adresse', 'retour_expediteur'] as $code) {
            $this->assertNull(
                $options->first(fn ($o) => $o->getIdentifier() === 'surcharge.'.$code),
                "Le supplément rebill {$code} ne doit pas être injecté au checkout",
            );
        }
        $this->assertSame(1890, $options->first(fn ($o) => $o->getIdentifier() === 'chronopost.chrono13')->price->value);
    }
}
