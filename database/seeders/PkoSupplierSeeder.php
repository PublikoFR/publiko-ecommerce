<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Pko\ShippingCommon\Models\Supplier;

/**
 * Fournisseurs de démonstration.
 *
 * Le champ `port_inclus` pilote la résolution du mode de port des produits
 * en `pko_port_mode = 'inherit'` (cf. PortModeResolver) :
 *   oui          → free     (port inclus dans le prix d'achat)
 *   non          → standard (grille transporteur classique)
 *   cas_par_cas  → standard (prudent) + remonte dans le filtre « Port à trancher »
 *
 * Les trois valeurs sont représentées ici pour que chaque branche de résolution
 * soit testable au checkout.
 *
 * Idempotent : `updateOrCreate` sur le nom, aucune suppression.
 */
class PkoSupplierSeeder extends Seeder
{
    /**
     * @var list<array{name:string, port_inclus:string, bl_neutre:bool, lead_time_min_days:int, lead_time_max_days:int, notes:string}>
     */
    private const SUPPLIERS = [
        [
            'name' => 'SOMFY',
            'port_inclus' => 'non',
            'bl_neutre' => false,
            'lead_time_min_days' => 5,
            'lead_time_max_days' => 10,
            'notes' => 'Fournisseur principal motorisation / volet roulant. Port facturé selon grille transporteur.',
        ],
        [
            'name' => 'Fournisseur Port Inclus',
            'port_inclus' => 'oui',
            'bl_neutre' => true,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 7,
            'notes' => 'Expédition directe en BL neutre, transport inclus dans le prix d\'achat.',
        ],
        [
            'name' => 'Fournisseur Port À Trancher',
            'port_inclus' => 'cas_par_cas',
            'bl_neutre' => false,
            'lead_time_min_days' => 7,
            'lead_time_max_days' => 15,
            'notes' => 'Politique de port variable selon la commande — à arbitrer produit par produit.',
        ],
    ];

    public function run(): void
    {
        foreach (self::SUPPLIERS as $supplier) {
            Supplier::query()->updateOrCreate(
                ['name' => $supplier['name']],
                $supplier,
            );
        }
    }
}
