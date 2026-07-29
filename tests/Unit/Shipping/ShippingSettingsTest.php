<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Pko\ShippingCommon\Settings\ShippingSettings;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;

/**
 * Tests de résolution DB → config → défaut pour ShippingSettings.
 *
 * RefreshDatabase est indispensable : Setting::set() persiste en base
 * (updateOrCreate), alors que Setting::forget() ne vide que le cache. Sans
 * rollback entre les tests, une valeur écrite par l'un fuite dans le suivant.
 */
class ShippingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    // ── thresholdCents ────────────────────────────────────────────────────────

    public function test_threshold_returns_default_50000_quand_aucune_config(): void
    {
        Config::set('shipping.franco.threshold_ht_cents', null);

        $this->assertSame(50000, ShippingSettings::thresholdCents());
    }

    public function test_threshold_lit_config_quand_aucune_valeur_db(): void
    {
        Config::set('shipping.franco.threshold_ht_cents', 35000);

        $this->assertSame(35000, ShippingSettings::thresholdCents());
    }

    public function test_threshold_db_gagne_sur_config(): void
    {
        Config::set('shipping.franco.threshold_ht_cents', 35000);
        Setting::set('shipping.franco.threshold_cents', 60000);

        $this->assertSame(60000, ShippingSettings::thresholdCents());
    }

    // ── francoServices ────────────────────────────────────────────────────────

    public function test_services_default_chrono13(): void
    {
        $this->assertSame(['chrono13'], ShippingSettings::francoServices());
    }

    public function test_services_db_gagne_sur_defaut(): void
    {
        Setting::set('shipping.franco.services', ['chrono13', 'chrono10']);

        $this->assertSame(['chrono13', 'chrono10'], ShippingSettings::francoServices());
    }

    public function test_services_filtre_les_valeurs_vides(): void
    {
        Setting::set('shipping.franco.services', ['chrono13', '', 'chrono10']);

        $result = ShippingSettings::francoServices();
        $this->assertNotContains('', $result);
        $this->assertContains('chrono13', $result);
        $this->assertContains('chrono10', $result);
    }

    // ── francoBasis ──────────────────────────────────────────────────────────

    public function test_basis_default_eligible_only(): void
    {
        $this->assertSame('eligible_only', ShippingSettings::francoBasis());
    }

    public function test_basis_db_cart_total(): void
    {
        Setting::set('shipping.franco.basis', 'cart_total');

        $this->assertSame('cart_total', ShippingSettings::francoBasis());
    }

    public function test_basis_valeur_inconnue_retombe_sur_eligible_only(): void
    {
        Setting::set('shipping.franco.basis', 'invalid_value');

        $this->assertSame('eligible_only', ShippingSettings::francoBasis());
    }

    // ── taxPriceBase ──────────────────────────────────────────────────────────

    public function test_tax_price_base_default_ht(): void
    {
        Config::set('shipping.tax.price_base', null);

        $this->assertSame('ht', ShippingSettings::taxPriceBase());
    }

    public function test_tax_price_base_lit_config(): void
    {
        Config::set('shipping.tax.price_base', 'ttc');

        $this->assertSame('ttc', ShippingSettings::taxPriceBase());
    }

    public function test_tax_price_base_db_gagne_sur_config(): void
    {
        Config::set('shipping.tax.price_base', 'ttc');
        Setting::set('shipping.tax.price_base', 'ht');

        $this->assertSame('ht', ShippingSettings::taxPriceBase());
    }

    // ── taxDisplay ────────────────────────────────────────────────────────────

    public function test_tax_display_default_both(): void
    {
        Config::set('shipping.tax.display', null);

        $this->assertSame('both', ShippingSettings::taxDisplay());
    }

    public function test_tax_display_db_gagne_sur_config(): void
    {
        Config::set('shipping.tax.display', 'ht');
        Setting::set('shipping.tax.display', 'ttc');

        $this->assertSame('ttc', ShippingSettings::taxDisplay());
    }

    public function test_tax_display_valeur_inconnue_retombe_sur_both(): void
    {
        Config::set('shipping.tax.display', 'invalid');

        $this->assertSame('both', ShippingSettings::taxDisplay());
    }
}
