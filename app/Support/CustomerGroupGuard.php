<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Lunar\Models\CustomerGroup;

/**
 * Politique de suppression d'un groupe client. Toutes les FK vers
 * `lunar_customer_groups` étant en NO ACTION (Lunar), un delete natif plante en
 * 1451 dès qu'un groupe est référencé. On bloque proprement (message FR) le
 * groupe par défaut, le groupe pro et tout groupe encore référencé.
 */
class CustomerGroupGuard
{
    /**
     * Tables pivot / références portant `customer_group_id`.
     *
     * @var array<string, string> label FR => table
     */
    private const REFERENCE_TABLES = [
        'client(s)' => 'lunar_customer_customer_group',
        'collection(s)' => 'lunar_collection_customer_group',
        'tarif(s)' => 'lunar_prices',
        'produit(s)' => 'lunar_customer_group_product',
        'méthode(s) de livraison' => 'lunar_customer_group_shipping_method',
        'remise(s)' => 'lunar_customer_group_discount',
        'zone(s) de taxe' => 'lunar_tax_zone_customer_groups',
    ];

    /** Retourne null si le groupe est supprimable, sinon le motif (FR) du blocage. */
    public static function blockReason(CustomerGroup $group): ?string
    {
        if ($group->default) {
            return 'groupe client par défaut, non supprimable.';
        }

        $proHandle = (string) config('customer-auth.default_customer_group_handle', 'installateurs');
        if ($group->handle === $proHandle) {
            return "utilisé par l'inscription professionnelle, non supprimable.";
        }

        $used = [];
        foreach (self::REFERENCE_TABLES as $label => $tableName) {
            $count = DB::table($tableName)->where('customer_group_id', $group->id)->count();
            if ($count > 0) {
                $used[] = $count.' '.$label;
            }
        }

        if ($used !== []) {
            return 'encore utilisé ('.implode(', ', $used)."). Détachez ces éléments d'abord.";
        }

        return null;
    }

    public static function isDeletable(CustomerGroup $group): bool
    {
        return self::blockReason($group) === null;
    }
}
