<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Contraintes SQL de visibilité storefront d'une catégorie, partagées par les
 * scopes `navVisible()` (Collection) et `storefrontVisible()` (Product).
 *
 * Une catégorie est visible si elle est activée, qu'aucun de ses ancêtres
 * nestedset n'est désactivé, et que sa chaîne de parents est intacte : une
 * catégorie (ou un de ses ancêtres) dont le `parent_id` pointe vers une ligne
 * disparue est un reliquat d'arbre supprimé. Elle n'apparaît dans aucun menu,
 * elle ne doit pas non plus rester joignable par son URL.
 */
final class CollectionVisibility
{
    /**
     * @param  Builder  $query  requête portant sur `lunar_collections`
     * @param  string  $table  nom ou alias de la table dans cette requête
     */
    public static function constrain(Builder $query, string $table = 'lunar_collections'): void
    {
        $query
            ->where("{$table}.pko_enabled", true)
            ->whereNotExists(function (Builder $q) use ($table): void {
                $q->from('lunar_collections as anc')
                    ->whereColumn('anc._lft', '<', "{$table}._lft")
                    ->whereColumn('anc._rgt', '>', "{$table}._rgt")
                    ->where('anc.pko_enabled', false);
            })
            ->whereNotExists(function (Builder $q) use ($table): void {
                $q->from('lunar_collections as orphan')
                    ->whereColumn('orphan._lft', '<=', "{$table}._lft")
                    ->whereColumn('orphan._rgt', '>=', "{$table}._rgt")
                    ->whereNotNull('orphan.parent_id')
                    ->whereNotExists(function (Builder $q2): void {
                        $q2->from('lunar_collections as parent')
                            ->whereColumn('parent.id', 'orphan.parent_id');
                    });
            });
    }
}
