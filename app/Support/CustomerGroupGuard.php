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
     * Rattachements explicites : toute ligne est une décision saisie à la main,
     * donc bloquante.
     *
     * @var array<string, string> label FR => table
     */
    private const REFERENCE_TABLES = [
        // NB : les clients ne bloquent PAS la suppression — ils sont détachés et
        // réattribués au groupe par défaut (cf. reassignCustomersToDefault()).
        'tarif(s)' => 'lunar_prices',
        'méthode(s) de livraison' => 'lunar_customer_group_shipping_method',
        'remise(s)' => 'lunar_customer_group_discount',
        'zone(s) de taxe' => 'lunar_tax_zone_customer_groups',
    ];

    /**
     * Visibilité catalogue : seules les lignes RESTRICTIVES bloquent.
     *
     * Ces pivots suivent la sémantique « pas de ligne = visible » (cf.
     * App\Support\CatalogAvailability). Les compter intégralement rendait tout
     * groupe indéracinable : Lunar y sème une ligne par collection et par produit
     * à la création de chacun, si bien qu'un groupe neuf apparaissait aussitôt
     * « utilisé (494 collections) » sans qu'on lui ait jamais rien rattaché.
     *
     * @var array<string, string> label FR => table
     */
    private const CATALOG_RESTRICTION_TABLES = [
        'restriction(s) sur des catégories' => 'lunar_collection_customer_group',
        'restriction(s) sur des produits' => 'lunar_customer_group_product',
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

        foreach (self::CATALOG_RESTRICTION_TABLES as $label => $tableName) {
            $count = CatalogAvailability::restrictionCount($tableName, (int) $group->id);
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

    /**
     * Détache tous les clients du groupe et leur (ré)attribue le groupe par
     * défaut ("Nouveau client"). À appeler avant la suppression d'un groupe :
     * sans ça, la FK lunar_customer_customer_group plante en 1451 et le client
     * se retrouverait sans aucun groupe.
     *
     * @return int nombre de clients réattribués
     */
    public static function reassignCustomersToDefault(CustomerGroup $group): int
    {
        $pivot = 'lunar_customer_customer_group';

        $customerIds = DB::table($pivot)
            ->where('customer_group_id', $group->id)
            ->pluck('customer_id');

        if ($customerIds->isEmpty()) {
            return 0;
        }

        $defaultHandle = (string) config('customer-auth.default_customer_group_handle', 'nouveau-client');
        $default = CustomerGroup::where('handle', $defaultHandle)->first();

        if ($default && $default->id !== $group->id) {
            $now = now();
            foreach ($customerIds as $customerId) {
                DB::table($pivot)->updateOrInsert(
                    ['customer_id' => $customerId, 'customer_group_id' => $default->id],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }
        }

        DB::table($pivot)->where('customer_group_id', $group->id)->delete();

        return $customerIds->count();
    }

    /**
     * Détache les lignes de visibilité catalogue résiduelles avant suppression.
     *
     * Un groupe jugé supprimable n'a par définition aucune ligne RESTRICTIVE, mais
     * il peut rester des lignes permissives (tous flags à 1) : redondantes avec le
     * défaut implicite, elles ne bloquent pas la suppression mais leur FK
     * NO ACTION la ferait échouer en 1451.
     *
     * @return int nombre de lignes détachées
     */
    public static function detachCatalogAvailability(CustomerGroup $group): int
    {
        $detached = 0;

        foreach (array_keys(CatalogAvailability::PIVOTS) as $table) {
            $detached += DB::table($table)->where('customer_group_id', $group->id)->delete();
        }

        return $detached;
    }
}
