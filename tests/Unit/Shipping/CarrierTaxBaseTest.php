<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use Pko\ShippingCommon\Support\ShippingTaxHelper;
use Tests\TestCase;

/**
 * Vérifie la reconversion TTC → net de ShippingTaxHelper (config price_base='ttc').
 *
 * Discriminant attendu : un prix de grille de 1200 cents TTC @ 20 % doit donner
 * un net de 1000 cents — identique à une grille HT de 1000 cents. La ligne de
 * port porte alors la même TVA (200) dans les deux modes.
 */
class CarrierTaxBaseTest extends TestCase
{
    public function test_gross_to_net_reconvertit_le_ttc_au_taux_reel(): void
    {
        $this->assertSame(1000, ShippingTaxHelper::grossToNet(1200, 0.20));
        $this->assertSame(2500, ShippingTaxHelper::grossToNet(3000, 0.20));
    }

    public function test_gross_to_net_neutre_quand_taux_nul_ou_prix_nul(): void
    {
        $this->assertSame(1500, ShippingTaxHelper::grossToNet(1500, 0.0));
        $this->assertSame(0, ShippingTaxHelper::grossToNet(0, 0.20));
    }
}
