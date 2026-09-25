<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\ShippingCommon\Mail\ShipmentCreatedMail;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Support\CarrierTrackingUrl;
use Tests\TestCase;

/**
 * Lien « Suivre mon colis » : page du transporteur, pas La Poste pour tout le monde.
 *
 * Régression : l'e-mail d'expédition d'un colis Chronopost pointait vers laposte.fr.
 */
class ShipmentTrackingUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_chronopost_pointe_vers_la_page_de_suivi_chronopost(): void
    {
        $this->assertSame(
            'https://www.chronopost.fr/tracking-no-cms/suivi-page?langue=fr_FR&listeNumerosLT=XN450178188FR',
            CarrierTrackingUrl::for('chronopost', 'XN450178188FR'),
        );
    }

    public function test_colissimo_et_inconnu_restent_sur_la_poste(): void
    {
        $this->assertStringStartsWith('https://www.laposte.fr/', CarrierTrackingUrl::for('colissimo', '6A123456789'));
        $this->assertStringStartsWith('https://www.laposte.fr/', CarrierTrackingUrl::for(null, '6A123456789'));
    }

    public function test_l_email_d_expedition_chronopost_contient_le_lien_chronopost(): void
    {
        $this->seed(DatabaseSeeder::class);

        $shipment = new CarrierShipment([
            'order_id' => 1,
            'carrier' => 'chronopost',
            'tracking_number' => 'XN450178188FR',
        ]);

        $mail = new ShipmentCreatedMail($shipment);

        $this->assertSame(CarrierTrackingUrl::for('chronopost', 'XN450178188FR'), $mail->values['tracking_url']);
        $mail->assertSeeInHtml('chronopost.fr/tracking-no-cms/suivi-page', false);
        $mail->assertDontSeeInHtml('laposte.fr', false);
    }
}
