<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\PkoShippingSurchargesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\ShippingCommon\Models\ShippingSurcharge;
use Pko\ShippingCommon\Support\ZoneResolver;
use Tests\TestCase;

/**
 * Tests des suppléments transport : seeder de référence + ZoneResolver.
 *
 * Les tests de comportement checkout (auto/quote au manifest) sont dans
 * tests/Unit/Shipping/ShippingCalculatorTest.php.
 */
class SurchargeModifierTest extends TestCase
{
    use RefreshDatabase;

    // ── ZoneResolver ──────────────────────────────────────────────────────────

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

    // ── Seeder de référence ────────────────────────────────────────────────────

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

        $this->assertSame('auto', ShippingSurcharge::query()->where('code', 'corse')->value('mode'));
        $this->assertSame('quote', ShippingSurcharge::query()->where('code', 'transport_specifique')->value('mode'));
        foreach (['assurance', 'correction_adresse', 'retour_expediteur'] as $code) {
            $this->assertSame('rebill', ShippingSurcharge::query()->where('code', $code)->value('mode'));
        }

        $this->assertSame(['type' => 'corse'], ShippingSurcharge::query()->where('code', 'corse')->value('rule'));
    }

    public function test_seeder_est_idempotent(): void
    {
        $this->seed(PkoShippingSurchargesSeeder::class);
        $this->seed(PkoShippingSurchargesSeeder::class);

        $this->assertSame(9, ShippingSurcharge::query()->count());
    }
}
