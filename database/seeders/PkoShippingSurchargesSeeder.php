<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Pko\ShippingCommon\Models\ShippingSurcharge;

class PkoShippingSurchargesSeeder extends Seeder
{
    public function run(): void
    {
        $surcharges = [
            ['code' => 'corse',              'label' => 'Supplément Corse',               'mode' => 'quote', 'amount_cents' => null],
            ['code' => 'zone_difficile',     'label' => 'Zone difficile d\'accès',         'mode' => 'quote', 'amount_cents' => null],
            ['code' => 'hors_normes',        'label' => 'Colis hors normes',              'mode' => 'quote', 'amount_cents' => null],
            ['code' => 'manutention',        'label' => 'Manutention spéciale',           'mode' => 'quote', 'amount_cents' => null],
            ['code' => 'livraison_samedi',   'label' => 'Livraison le samedi',            'mode' => 'auto',  'amount_cents' => null],
            ['code' => 'assurance',          'label' => 'Assurance marchandise',          'mode' => 'rebill', 'amount_cents' => null],
            ['code' => 'correction_adresse', 'label' => 'Correction d\'adresse',          'mode' => 'rebill', 'amount_cents' => null],
            ['code' => 'retour_expediteur',  'label' => 'Retour à l\'expéditeur',          'mode' => 'rebill', 'amount_cents' => null],
            ['code' => 'transport_specifique', 'label' => 'Transport spécifique produit',  'mode' => 'quote', 'amount_cents' => null],
        ];

        foreach ($surcharges as $data) {
            ShippingSurcharge::query()->updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['enabled' => true]),
            );
        }
    }
}
