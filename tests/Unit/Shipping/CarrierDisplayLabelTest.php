<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use Pko\ShippingCommon\Support\CarrierDisplayLabel;
use Tests\TestCase;

class CarrierDisplayLabelTest extends TestCase
{
    public function test_le_transporteur_est_traduit(): void
    {
        $this->assertSame('Chronopost', CarrierDisplayLabel::carrier('chronopost'));
        $this->assertSame('Colissimo', CarrierDisplayLabel::carrier('colissimo'));
        $this->assertSame('autre', CarrierDisplayLabel::carrier('autre'));
        $this->assertSame('—', CarrierDisplayLabel::carrier(null));
    }

    public function test_le_statut_d_envoi_est_traduit(): void
    {
        $this->assertSame('En attente', CarrierDisplayLabel::status('pending'));
        $this->assertSame('Créé', CarrierDisplayLabel::status('created'));
        $this->assertSame('Échec', CarrierDisplayLabel::status('failed'));
        $this->assertSame('inconnu', CarrierDisplayLabel::status('inconnu'));
    }

    public function test_un_service_inconnu_retombe_sur_le_slug(): void
    {
        $this->assertSame('service-invente', CarrierDisplayLabel::service('chronopost', 'service-invente'));
        $this->assertSame('—', CarrierDisplayLabel::service('chronopost', null));
    }
}
