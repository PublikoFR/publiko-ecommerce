<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use Pko\ShippingCommon\Modifiers\AbstractCarrierModifier;
use Tests\TestCase;

/**
 * Vérifie la reconversion TTC → net du modifier transporteur (config price_base='ttc').
 *
 * Discriminant attendu : un prix de grille de 1200 cents TTC @ 20 % doit donner
 * un net de 1000 cents — identique à une grille HT de 1000 cents. La ligne de
 * port porte alors la même TVA (200) dans les deux modes.
 */
class CarrierTaxBaseTest extends TestCase
{
    private function modifier(): object
    {
        return new class extends AbstractCarrierModifier
        {
            protected function carrierCode(): string
            {
                return 'test';
            }

            public function netFor(int $gross, float $rate): int
            {
                return $this->grossToNet($gross, $rate);
            }
        };
    }

    public function test_gross_to_net_reconvertit_le_ttc_au_taux_reel(): void
    {
        $m = $this->modifier();

        $this->assertSame(1000, $m->netFor(1200, 0.20));
        $this->assertSame(2500, $m->netFor(3000, 0.20));
    }

    public function test_gross_to_net_neutre_quand_taux_nul_ou_prix_nul(): void
    {
        $m = $this->modifier();

        $this->assertSame(1500, $m->netFor(1500, 0.0));
        $this->assertSame(0, $m->netFor(0, 0.20));
    }
}
