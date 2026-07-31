<?php

declare(strict_types=1);

namespace Tests\Unit\Storefront;

use Pko\Storefront\Support\StorefrontText;
use Tests\TestCase;

/**
 * Les bandeaux du front doivent refléter le seuil de franco réglé en
 * back-office, sans recopie en dur.
 */
class StorefrontTextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Pas de DB dans ce test : ShippingSettings retombe sur la config.
        config(['shipping.franco.threshold_ht_cents' => 50000]);
    }

    public function test_it_resolves_the_franco_placeholder(): void
    {
        $this->assertSame(
            'Livraison offerte dès 500 € HT',
            StorefrontText::render('Livraison offerte dès {{port_franco}}'),
        );
    }

    public function test_it_tolerates_spacing_and_case(): void
    {
        $this->assertSame(
            'dès 500 € HT',
            StorefrontText::render('dès {{  PORT_FRANCO }}'),
        );
    }

    public function test_it_renders_the_amount_only_variant(): void
    {
        $this->assertSame('500 €', StorefrontText::render('{{port_franco_montant}}'));
    }

    public function test_it_follows_the_configured_threshold(): void
    {
        config(['shipping.franco.threshold_ht_cents' => 12550]);

        $this->assertSame('125,50 € HT', StorefrontText::render('{{port_franco}}'));
    }

    public function test_it_leaves_unknown_placeholders_untouched(): void
    {
        $this->assertSame('{{inconnu}}', StorefrontText::render('{{inconnu}}'));
    }

    public function test_it_handles_null_and_plain_text(): void
    {
        $this->assertSame('', StorefrontText::render(null));
        $this->assertSame('Retrait en magasin', StorefrontText::render('Retrait en magasin'));
    }
}
