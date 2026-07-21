<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\TaxZone;
use Lunar\Shipping\Models\ShippingMethod;

/**
 * Calcule ce que la suppression d'un groupe client entraînerait.
 *
 * Les liaisons se cascadent dans les deux sens : Lunar détache déjà le groupe
 * quand on supprime une réduction, une méthode de livraison, un produit ou une
 * catégorie (observers `deleting`) ; ici on couvre le sens inverse.
 *
 * Deux cas à distinguer :
 *
 *   - **liaison partagée** — l'objet reste rattaché à d'autres groupes. On
 *     détache sans rien demander, c'est sans conséquence.
 *   - **liaison exclusive** — le groupe supprimé est le DERNIER rattaché. Détacher
 *     rendrait l'objet orphelin, et le scope Lunar est un `whereHas` : sans aucun
 *     groupe, une réduction ne s'applique plus à personne, silencieusement. On
 *     remonte donc le cas pour que l'utilisateur tranche.
 *
 * Les tarifs sont traités à part : `lunar_prices.customer_group_id` est nullable,
 * et un prix à NULL vaut pour TOUS les groupes. Les « détacher » publierait des
 * tarifs négociés en prix public — jamais. Ils ne peuvent qu'être supprimés avec
 * le groupe, ou empêcher la suppression.
 */
class CustomerGroupDeletionImpact
{
    /**
     * Pivots dont l'objet lié peut devenir orphelin.
     *
     * @var array<string, array{fk: string, model_table: string, label: string, name_column: string}>
     */
    private const LINKS = [
        'lunar_customer_group_discount' => [
            'fk' => 'discount_id',
            'model_table' => 'lunar_discounts',
            'model' => Discount::class,
            'label' => 'réduction',
            'name_column' => 'name',
            'enabled_column' => 'enabled',
        ],
        'lunar_customer_group_shipping_method' => [
            'fk' => 'shipping_method_id',
            'model_table' => 'lunar_shipping_methods',
            'model' => ShippingMethod::class,
            'label' => 'méthode de livraison',
            'name_column' => 'name',
            'enabled_column' => 'enabled',
        ],
        'lunar_tax_zone_customer_groups' => [
            'fk' => 'tax_zone_id',
            'model_table' => 'lunar_tax_zones',
            'model' => TaxZone::class,
            'label' => 'zone de taxe',
            'name_column' => 'name',
            // Ce pivot ne porte pas de flag : toute ligne vaut rattachement.
            'enabled_column' => null,
        ],
    ];

    /**
     * @return array{
     *     orphans: array<int, array{table: string, fk: string, id: int, label: string, name: string}>,
     *     shared: int,
     *     prices: int,
     *     customers: int
     * }
     */
    public static function analyse(CustomerGroup $group): array
    {
        $orphans = [];
        $shared = 0;

        foreach (self::LINKS as $pivot => $config) {
            $fk = $config['fk'];
            $enabledColumn = $config['enabled_column'];

            // On ne raisonne que sur les liaisons ACTIVES. Lunar sème une ligne par
            // groupe à la création d'une réduction ou d'une méthode de livraison
            // (trait HasCustomerGroups), à `enabled = false` : une telle ligne ne
            // rattache rien, la supprimer ne change rien. Sans ce filtre, toute
            // réduction paraîtrait rattachée à tous les groupes.
            $linkedIds = DB::table($pivot)
                ->where('customer_group_id', $group->id)
                ->when($enabledColumn !== null, fn ($q) => $q->where($enabledColumn, true))
                ->pluck($fk)
                ->filter()
                ->unique();

            foreach ($linkedIds as $linkedId) {
                // Reste-t-il un AUTRE groupe pour lequel l'objet est actif ?
                $otherGroups = DB::table($pivot)
                    ->where($fk, $linkedId)
                    ->where('customer_group_id', '!=', $group->id)
                    ->when($enabledColumn !== null, fn ($q) => $q->where($enabledColumn, true))
                    ->count();

                if ($otherGroups > 0) {
                    $shared++;

                    continue;
                }

                $name = DB::table($config['model_table'])
                    ->where('id', $linkedId)
                    ->value($config['name_column']);

                $orphans[] = [
                    'table' => $config['model_table'],
                    'model' => $config['model'],
                    'fk' => $fk,
                    'pivot' => $pivot,
                    'id' => (int) $linkedId,
                    'label' => $config['label'],
                    'name' => (string) ($name ?: "#{$linkedId}"),
                ];
            }
        }

        return [
            'orphans' => $orphans,
            'shared' => $shared,
            'prices' => DB::table('lunar_prices')->where('customer_group_id', $group->id)->count(),
            'customers' => DB::table('lunar_customer_customer_group')
                ->where('customer_group_id', $group->id)->count(),
        ];
    }

    /**
     * Détache tout ce qui référence le groupe, SANS le supprimer lui-même.
     *
     * Appelé depuis le `before()` de la DeleteAction Filament : c'est Filament qui
     * exécute ensuite le `$record->delete()` natif et gère la redirection.
     *
     * @param  bool  $deleteOrphans  supprimer les objets devenus orphelins, ou les
     *                               conserver détachés (ils seront alors inactifs)
     */
    public static function prepare(CustomerGroup $group, bool $deleteOrphans): void
    {
        DB::transaction(function () use ($group, $deleteOrphans): void {
            $impact = self::analyse($group);

            // Les tarifs ne peuvent pas survivre : NULL les rendrait publics.
            DB::table('lunar_prices')->where('customer_group_id', $group->id)->delete();

            CustomerGroupGuard::reassignCustomersToDefault($group);
            CustomerGroupGuard::detachCatalogAvailability($group);

            foreach (array_keys(self::LINKS) as $pivot) {
                DB::table($pivot)->where('customer_group_id', $group->id)->delete();
            }

            if ($deleteOrphans) {
                foreach ($impact['orphans'] as $orphan) {
                    // IMPÉRATIF : passer par Eloquent, jamais par DB::table().
                    // Ces modèles nettoient leurs dépendances dans `deleting()`
                    // (ShippingMethod → shippingRates, TaxZone → taxRates,
                    // Discount → discountables). Un DELETE brut les court-circuite
                    // et fait planter la suppression en 1451 sur la table enfant.
                    $orphan['model']::find($orphan['id'])?->delete();
                }
            }
        });
    }

    /** Détache puis supprime le groupe. Utilisé par le bulk delete. */
    public static function apply(CustomerGroup $group, bool $deleteOrphans): void
    {
        self::prepare($group, $deleteOrphans);
        $group->delete();
    }

    /**
     * Résumé lisible de l'impact, pour la modale de confirmation.
     *
     * @param  array{orphans: array<int, array{label: string, name: string}>, shared: int, prices: int, customers: int}  $impact
     * @return array<int, string>
     */
    public static function summarise(array $impact): array
    {
        $lines = [];

        if ($impact['customers'] > 0) {
            $lines[] = $impact['customers'].' client(s) seront réattribués au groupe par défaut.';
        }

        if ($impact['prices'] > 0) {
            $lines[] = $impact['prices'].' tarif(s) propres à ce groupe seront supprimés définitivement.';
        }

        if ($impact['shared'] > 0) {
            $lines[] = $impact['shared'].' liaison(s) seront détachées (les éléments restent rattachés à d’autres groupes).';
        }

        return $lines;
    }
}
