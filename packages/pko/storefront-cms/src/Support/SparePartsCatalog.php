<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Support;

use Lunar\Models\Collection;
use Lunar\Models\Product;
use Lunar\Models\Url;
use Pko\StorefrontCms\Models\Setting;

/**
 * Arborescence « Pièces détachées » du catalogue : une collection racine
 * désignée par réglage (Storefront → Paramètres → Catalogue), et tous ses
 * descendants (nestedset `_lft` / `_rgt`, même groupe de collections).
 *
 * Réglage vide ou collection supprimée → repli sur la collection dont l'URL a
 * le slug `pieces-detachees`. Aucune des deux → pas d'arborescence (rien n'est
 * considéré comme pièce détachée).
 */
final class SparePartsCatalog
{
    /** Clé `pko_storefront_settings` : id de la collection racine. */
    public const SETTING_KEY = 'catalog.spare_parts_collection_id';

    /** Slug de repli quand le réglage est vide. */
    public const DEFAULT_SLUG = 'pieces-detachees';

    public static function root(): ?Collection
    {
        $modelClass = Collection::modelClass();
        $id = Setting::get(self::SETTING_KEY);

        if (is_numeric($id) && (int) $id > 0) {
            $collection = $modelClass::query()->find((int) $id);
            if ($collection instanceof Collection) {
                return $collection;
            }
        }

        $url = Url::query()
            ->where('slug', self::DEFAULT_SLUG)
            ->where('element_type', (new $modelClass)->getMorphClass())
            ->first();

        $element = $url?->element;

        return $element instanceof Collection ? $element : null;
    }

    /** La collection est la racine ou l'un de ses descendants. */
    public static function containsCollection(Collection $collection, ?Collection $root = null): bool
    {
        $root ??= self::root();
        if ($root === null) {
            return false;
        }

        return (int) $collection->collection_group_id === (int) $root->collection_group_id
            && (int) $collection->_lft >= (int) $root->_lft
            && (int) $collection->_rgt <= (int) $root->_rgt;
    }

    /** Le produit est rattaché à au moins une collection de l'arborescence. */
    public static function containsProduct(Product $product, ?Collection $root = null): bool
    {
        $root ??= self::root();
        if ($root === null) {
            return false;
        }

        return $product->collections()
            ->where('lunar_collections.collection_group_id', $root->collection_group_id)
            ->where('lunar_collections._lft', '>=', $root->_lft)
            ->where('lunar_collections._rgt', '<=', $root->_rgt)
            ->exists();
    }

    /**
     * Options du sélecteur admin : toutes les collections, dans l'ordre de
     * l'arbre, indentées par profondeur.
     *
     * @return array<int, string>
     */
    public static function collectionOptions(): array
    {
        $modelClass = Collection::modelClass();

        // Profondeur calculée via parent_id : `withDepth()` de nestedset ne
        // tient pas compte du scope `collection_group_id` (profondeur -1).
        $collections = $modelClass::query()
            ->orderBy('collection_group_id')
            ->orderBy('_lft')
            ->get();

        $parents = $collections->pluck('parent_id', 'id')->all();
        $depth = function (int $id) use ($parents): int {
            $level = 0;
            while (($id = (int) ($parents[$id] ?? 0)) > 0 && $level < 50) {
                $level++;
            }

            return $level;
        };

        return $collections
            ->mapWithKeys(fn (Collection $c): array => [
                $c->id => str_repeat('— ', $depth((int) $c->id)).((string) $c->translateAttribute('name') ?: '#'.$c->id),
            ])
            ->all();
    }
}
