<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use Pko\Account\Support\OrderStatusLabel;
use Pko\Account\Support\PaymentDisplayLabel;
use Tests\TestCase;

class OrderStatusLabelTest extends TestCase
{
    public function test_il_lit_le_libelle_dans_la_config_lunar(): void
    {
        $this->assertSame('Paiement reçu', OrderStatusLabel::of('payment-received'));
        $this->assertSame('Paiement reçu', \order_status_label('payment-received'));
    }

    public function test_un_slug_inconnu_est_affiche_tel_quel(): void
    {
        $this->assertSame('statut-invente', OrderStatusLabel::of('statut-invente'));
        $this->assertSame('statut-invente', \order_status_label('statut-invente'));
    }

    public function test_une_valeur_vide_donne_une_chaine_vide(): void
    {
        $this->assertSame('', OrderStatusLabel::of(null));
        $this->assertSame('', OrderStatusLabel::of(''));
        $this->assertSame('', \order_status_label(null));
    }

    public function test_options_expose_les_statuts_configures(): void
    {
        $options = OrderStatusLabel::options();

        $this->assertArrayHasKey('payment-received', $options);
        $this->assertSame('Paiement reçu', $options['payment-received']);
        $this->assertSame('En attente de devis', $options['awaiting-quote']);
    }

    public function test_le_driver_de_paiement_est_traduit(): void
    {
        $this->assertSame('Carte bancaire', PaymentDisplayLabel::driver('stripe'));
        $this->assertSame('Carte bancaire', \payment_driver_label('stripe'));
        $this->assertSame('inconnu', \payment_driver_label('inconnu'));
        $this->assertSame('—', \payment_driver_label(null));
    }

    public function test_le_statut_de_transaction_reuse_le_libelle_commande(): void
    {
        $this->assertSame('Paiement reçu', PaymentDisplayLabel::status('payment-received'));
        $this->assertSame('Réussi', PaymentDisplayLabel::status('succeeded'));
        $this->assertSame('truc-inconnu', \payment_status_label('truc-inconnu'));
    }
}
