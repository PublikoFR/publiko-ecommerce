<?php

declare(strict_types=1);

namespace App\Observers;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Collection;

/**
 * Nettoie les tables pivot référençant `lunar_collections` avant la suppression
 * d'une collection (catégorie).
 *
 * Pourquoi : la suppression d'un nœud nested set efface tout le sous-arbre via
 * une requête SQL brute (`delete ... where _lft between ? and ?`) — les events
 * Eloquent ne se déclenchent donc PAS pour les descendants. Leurs lignes pivot
 * (customer_group, product, discount, brand, feature_family) restent et les FK
 * NO ACTION font échouer le delete en 1451 :
 *   « Cannot delete or update a parent row: a foreign key constraint fails
 *     (lunar_collection_customer_group ...) ».
 *
 * On détache donc, dès le `deleting` du nœud racine, tous les pivots de la
 * collection ET de ses descendants (récupérés avant leur suppression).
 */
class CollectionDeleteObserver
{
    /** @var array<int, string> pivots à FK vers lunar_collections.id */
    private const PIVOT_TABLES = [
        'lunar_collection_customer_group',
        'lunar_collection_product',
        'lunar_collection_discount',
        'lunar_brand_collection',
        'pko_feature_family_collection',
    ];

    public function deleting(Collection $collection): void
    {
        // Racine + tout le sous-arbre (les descendants existent encore à ce stade).
        $ids = $collection->descendants()->pluck('id')->push($collection->getKey())->all();

        foreach (self::PIVOT_TABLES as $table) {
            DB::table($table)->whereIn('collection_id', $ids)->delete();
        }
    }
}
