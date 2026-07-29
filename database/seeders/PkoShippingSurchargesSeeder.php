<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Pko\ShippingCommon\Models\ShippingSurcharge;

class PkoShippingSurchargesSeeder extends Seeder
{
    /**
     * Les 9 suppléments de référence.
     *
     * Modes :
     *   auto   — majore le prix de chaque option carrier du manifest (rule géographique obligatoire).
     *   quote  — injecte une option sentinel price=0 quand la rule matche.
     *   rebill — hors flux checkout, refacturation a posteriori.
     *
     * Rules JSON supportées par ShippingCalculator::matchesAddress() :
     *   {"type":"corse"}          → ZoneResolver::isCorse()
     *   {"type":"zone_difficile"} → placeholder, ZoneResolver::isZoneDifficile() à implémenter
     *   {"postcode_prefix":"XX"}  → str_starts_with(postcode, "XX")
     *   {"match":"always"}        → toujours vrai (tous les destinataires)
     *   null                      → jamais vrai (désactivé logiquement au matching)
     *
     * Enabled par défaut : uniquement les surcharges immédiatement exploitables ou les rebill.
     * Les surcharges auto/quote sans implémentation complète sont livrées disabled=false.
     */
    public function run(): void
    {
        $surcharges = [
            // ── Mode auto : majoration géographique ─────────────────────────
            [
                'code' => 'corse',
                'label' => 'Supplément Corse',
                'mode' => 'auto',
                'rule' => ['type' => 'corse'],
                'amount_cents' => 800,   // 8,00 € HT — modifiable via admin
                'enabled' => true,
            ],
            [
                'code' => 'zone_difficile',
                'label' => 'Zone difficile d\'accès',
                'mode' => 'auto',
                'rule' => ['type' => 'zone_difficile'],  // ZoneResolver::isZoneDifficile() à implémenter
                'amount_cents' => 500,   // 5,00 € HT — placeholder, editable
                'enabled' => false, // désactivé jusqu'à l'implémentation ZoneResolver
            ],
            [
                'code' => 'livraison_samedi',
                'label' => 'Livraison le samedi',
                'mode' => 'auto',
                'rule' => ['match' => 'always'],  // majore toutes les options carriers
                'amount_cents' => 1500,  // 15,00 € HT
                'enabled' => false, // désactivé par défaut (activer selon accord transporteur)
            ],
            // ── Mode quote : options sentinel sur devis ──────────────────────
            [
                'code' => 'hors_normes',
                'label' => 'Colis hors normes',
                'mode' => 'quote',
                'rule' => ['type' => 'hors_normes'],  // déclenché par le produit, pas l'adresse
                'amount_cents' => null,
                'enabled' => false,
            ],
            [
                'code' => 'manutention',
                'label' => 'Manutention spéciale',
                'mode' => 'quote',
                'rule' => ['type' => 'manutention'],
                'amount_cents' => null,
                'enabled' => false,
            ],
            [
                'code' => 'transport_specifique',
                'label' => 'Transport spécifique produit',
                'mode' => 'quote',
                'rule' => ['type' => 'transport_specifique'],
                'amount_cents' => null,
                'enabled' => false,
            ],
            // ── Mode rebill : refacturation a posteriori ─────────────────────
            [
                'code' => 'assurance',
                'label' => 'Assurance marchandise',
                'mode' => 'rebill',
                'rule' => null,
                'amount_cents' => null,
                'enabled' => true,
            ],
            [
                'code' => 'correction_adresse',
                'label' => 'Correction d\'adresse',
                'mode' => 'rebill',
                'rule' => null,
                'amount_cents' => null,
                'enabled' => true,
            ],
            [
                'code' => 'retour_expediteur',
                'label' => 'Retour à l\'expéditeur',
                'mode' => 'rebill',
                'rule' => null,
                'amount_cents' => null,
                'enabled' => true,
            ],
        ];

        foreach ($surcharges as $data) {
            ShippingSurcharge::query()->updateOrCreate(
                ['code' => $data['code']],
                $data,
            );
        }
    }
}
