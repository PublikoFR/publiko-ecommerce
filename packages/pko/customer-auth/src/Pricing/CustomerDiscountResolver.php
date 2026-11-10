<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Pricing;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Lunar\Facades\Pricing;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Contracts\Currency as CurrencyContract;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\CustomerAuth\Models\CustomerDiscount;
use Pko\CustomerAuth\Models\NegotiatedPrice;

/**
 * Moteur des remises client (%) et du prix négocié — service réutilisable.
 *
 * Règles (spec arbitrée) :
 *  - remise applicable = la PLUS FORTE des remises du client dont la cible couvre
 *    le produit (collection du produit ou un de ses ancêtres, famille fabricant,
 *    marque) — pas de cumul entre remises ;
 *  - produit `pko_not_discountable` → aucune remise % (le prix négocié reste valable) ;
 *  - prix final = min(prix déjà résolu par Lunar, prix négocié, prix remisé), le
 *    prix remisé étant calculé sur le prix déjà résolu (jamais sur le négocié).
 *
 * Enregistré en `scoped` (une instance par requête / job) : les remises d'un
 * client et les cibles d'un produit sont chargées UNE fois puis mémorisées, ce
 * qui évite le N+1 sur les listings. `preloadProducts()` / `preloadVariants()`
 * chargent les cibles d'un lot de produits en 2 requêtes.
 */
class CustomerDiscountResolver
{
    /**
     * Remises par jeu de clients : clé "1,5" →
     * ['collection' => [collectionId => bp], 'brand_family' => [...], 'brand' => [...]].
     * Les collections sont déjà étendues à toute leur descendance.
     *
     * @var array<string, array<string, array<int, int>>>
     */
    private array $discounts = [];

    /**
     * Cibles d'un produit.
     *
     * @var array<int, array{brand: ?int, brand_family: ?int, not_discountable: bool, collections: list<int>}>
     */
    private array $products = [];

    /** @var array<string, ?int> clé "clients|variante|devise" → prix négocié (cents) */
    private array $negotiated = [];

    private ?CustomerPriceBreakdown $last = null;

    /** Remise % suspendue (calcul du panier « promotions » par CartDiscountArbiter). */
    private bool $percentSuspended = false;

    /**
     * Prix client détaillé d'une variante. Passe par le PricingManager Lunar (donc
     * par NegotiatedPricePipeline) : même résultat que partout ailleurs.
     */
    public function priceFor(
        ProductVariant $variant,
        ?Authenticatable $user = null,
        ?CurrencyContract $currency = null,
        int $qty = 1,
    ): CustomerPriceBreakdown {
        $manager = Pricing::for($variant)->qty($qty);

        if ($user !== null) {
            $manager->user($user);
        }

        if ($currency !== null) {
            $manager->currency($currency);
        }

        $this->last = null;
        $matched = $manager->get()->matched;
        $breakdown = $this->last;
        $this->last = null;

        if ($breakdown instanceof CustomerPriceBreakdown && $breakdown->finalCents === $matched->price->value) {
            return $breakdown;
        }

        return CustomerPriceBreakdown::catalog($matched->price->value);
    }

    /**
     * Calcul pur (appelé par le pipeline) : applique prix négocié et remise au prix
     * déjà résolu par Lunar.
     *
     * @param  list<int>  $customerIds
     */
    public function breakdown(
        ProductVariant $variant,
        int $resolvedCents,
        array $customerIds,
        int $currencyId,
    ): CustomerPriceBreakdown {
        $final = $resolvedCents;
        $source = CustomerPriceBreakdown::SOURCE_CATALOG;

        $negotiated = $this->negotiatedCents($variant, $customerIds, $currencyId);
        if ($negotiated !== null && $negotiated < $final) {
            $final = $negotiated;
            $source = CustomerPriceBreakdown::SOURCE_NEGOTIATED;
        }

        $bp = $this->percentSuspended ? null : $this->bestBasisPoints($variant, $customerIds);
        $discounted = null;
        if ($bp !== null) {
            $discounted = self::applyBasisPoints($resolvedCents, $bp);
            if ($discounted < $final) {
                $final = $discounted;
                $source = CustomerPriceBreakdown::SOURCE_DISCOUNT;
            }
        }

        return $this->last = new CustomerPriceBreakdown(
            originalCents: $resolvedCents,
            finalCents: $final,
            source: $source,
            negotiatedCents: $negotiated,
            discountBasisPoints: $bp,
            discountedCents: $discounted,
        );
    }

    /**
     * Plus forte remise (points de base) couvrant le produit de la variante, null
     * si aucune ou produit non remisable.
     *
     * @param  list<int>  $customerIds
     */
    public function bestBasisPoints(ProductVariant|Product $purchasable, array $customerIds): ?int
    {
        $discounts = $this->discountsFor($customerIds);

        if ($discounts === null) {
            return null;
        }

        $targets = $purchasable instanceof Product
            ? $this->productTargets($purchasable)
            : $this->variantTargets($purchasable);

        if ($targets === null || $targets['not_discountable']) {
            return null;
        }

        $best = 0;

        foreach ($targets['collections'] as $collectionId) {
            $best = max($best, $discounts[CustomerDiscount::TARGET_COLLECTION][$collectionId] ?? 0);
        }

        if ($targets['brand_family'] !== null) {
            $best = max($best, $discounts[CustomerDiscount::TARGET_BRAND_FAMILY][$targets['brand_family']] ?? 0);
        }

        if ($targets['brand'] !== null) {
            $best = max($best, $discounts[CustomerDiscount::TARGET_BRAND][$targets['brand']] ?? 0);
        }

        return $best > 0 ? $best : null;
    }

    /**
     * Exécute $callback avec la remise client % désactivée (prix négocié conservé) :
     * sert à calculer le prix « non remisé » sur lequel s'appliquent les promotions.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutPercentDiscount(Closure $callback): mixed
    {
        $previous = $this->percentSuspended;
        $this->percentSuspended = true;

        try {
            return $callback();
        } finally {
            $this->percentSuspended = $previous;
        }
    }

    /** @param  list<int>  $customerIds */
    public function hasDiscounts(array $customerIds): bool
    {
        return $this->discountsFor($customerIds) !== null;
    }

    /** @return list<int> */
    public function customerIdsFor(?Authenticatable $user): array
    {
        if ($user === null || ! method_exists($user, 'customers')) {
            return [];
        }

        return $user->customers->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Charge en 2 requêtes les cibles (marque, famille fabricant, non-remisable,
     * collections) d'un lot de produits — à appeler avant de rendre un listing.
     *
     * @param  iterable<Product|int>  $products
     */
    public function preloadProducts(iterable $products): void
    {
        $ids = [];
        foreach ($products as $product) {
            $id = $product instanceof Product ? (int) $product->getKey() : (int) $product;
            if ($id > 0 && ! isset($this->products[$id])) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return;
        }

        $this->loadProductTargets(array_values($ids));
    }

    /** @param  iterable<ProductVariant>  $variants */
    public function preloadVariants(iterable $variants): void
    {
        $productIds = [];
        foreach ($variants as $variant) {
            $productIds[] = (int) $variant->product_id;
        }

        $this->preloadProducts($productIds);
    }

    /** Vide les mémos (tests, ou après modification des remises dans la requête). */
    public function flush(): void
    {
        $this->discounts = [];
        $this->products = [];
        $this->negotiated = [];
        $this->last = null;
    }

    /** Prix remisé en centimes, arrondi au centime le plus proche (calcul entier). */
    public static function applyBasisPoints(int $cents, int $basisPoints): int
    {
        return intdiv($cents * (10000 - $basisPoints) + 5000, 10000);
    }

    /** @param  list<int>  $customerIds */
    private function negotiatedCents(ProductVariant $variant, array $customerIds, int $currencyId): ?int
    {
        if ($customerIds === []) {
            return null;
        }

        $key = self::customerKey($customerIds).'|'.$variant->id.'|'.$currencyId;

        if (! array_key_exists($key, $this->negotiated)) {
            $min = NegotiatedPrice::query()
                ->whereIn('customer_id', $customerIds)
                ->where('product_variant_id', $variant->id)
                ->where('currency_id', $currencyId)
                ->min('price');

            $this->negotiated[$key] = $min !== null ? (int) $min : null;
        }

        return $this->negotiated[$key];
    }

    /**
     * Remises indexées des clients, null s'ils n'en ont aucune (court-circuit :
     * aucune requête produit dans ce cas).
     *
     * @param  list<int>  $customerIds
     * @return array<string, array<int, int>>|null
     */
    private function discountsFor(array $customerIds): ?array
    {
        if ($customerIds === []) {
            return null;
        }

        $key = self::customerKey($customerIds);

        if (! array_key_exists($key, $this->discounts)) {
            $this->discounts[$key] = $this->loadDiscounts($customerIds);
        }

        $map = $this->discounts[$key];

        return $map[CustomerDiscount::TARGET_COLLECTION] === []
            && $map[CustomerDiscount::TARGET_BRAND_FAMILY] === []
            && $map[CustomerDiscount::TARGET_BRAND] === []
            ? null
            : $map;
    }

    /**
     * @param  list<int>  $customerIds
     * @return array<string, array<int, int>>
     */
    private function loadDiscounts(array $customerIds): array
    {
        $map = array_fill_keys(CustomerDiscount::TARGET_TYPES, []);

        $rows = CustomerDiscount::query()
            ->whereIn('customer_id', $customerIds)
            ->get(['target_type', 'target_id', 'percent']);

        foreach ($rows as $row) {
            $bp = $row->basisPoints();
            if ($bp <= 0 || ! array_key_exists($row->target_type, $map)) {
                continue;
            }
            $map[$row->target_type][$row->target_id] = max($map[$row->target_type][$row->target_id] ?? 0, $bp);
        }

        $map[CustomerDiscount::TARGET_COLLECTION] = $this->expandCollections($map[CustomerDiscount::TARGET_COLLECTION]);

        return $map;
    }

    /**
     * Étend les remises collection à toute la descendance (nestedset, arbre par
     * groupe de collections) : collectionId → plus forte remise d'un ancêtre-ou-soi.
     *
     * @param  array<int, int>  $byTarget
     * @return array<int, int>
     */
    private function expandCollections(array $byTarget): array
    {
        if ($byTarget === []) {
            return [];
        }

        $roots = LunarCollection::query()
            ->whereIn('id', array_keys($byTarget))
            ->get(['id', '_lft', '_rgt', 'collection_group_id']);

        if ($roots->isEmpty()) {
            return [];
        }

        $nodes = LunarCollection::query()
            ->where(function ($query) use ($roots): void {
                foreach ($roots as $root) {
                    $query->orWhere(function ($q) use ($root): void {
                        $q->where('collection_group_id', $root->collection_group_id)
                            ->where('_lft', '>=', $root->_lft)
                            ->where('_rgt', '<=', $root->_rgt);
                    });
                }
            })
            ->get(['id', '_lft', '_rgt', 'collection_group_id']);

        $covered = [];
        foreach ($nodes as $node) {
            foreach ($roots as $root) {
                if ((int) $node->collection_group_id === (int) $root->collection_group_id
                    && $node->_lft >= $root->_lft
                    && $node->_rgt <= $root->_rgt) {
                    $covered[(int) $node->id] = max($covered[(int) $node->id] ?? 0, $byTarget[(int) $root->id]);
                }
            }
        }

        return $covered;
    }

    /** @return array{brand: ?int, brand_family: ?int, not_discountable: bool, collections: list<int>}|null */
    private function variantTargets(ProductVariant $variant): ?array
    {
        if ($variant->relationLoaded('product') && $variant->product instanceof Product) {
            return $this->productTargets($variant->product);
        }

        $productId = (int) $variant->product_id;
        if ($productId <= 0) {
            return null;
        }

        if (! isset($this->products[$productId])) {
            $this->loadProductTargets([$productId]);
        }

        return $this->products[$productId] ?? null;
    }

    /** @return array{brand: ?int, brand_family: ?int, not_discountable: bool, collections: list<int>} */
    private function productTargets(Product $product): array
    {
        $id = (int) $product->getKey();

        if (isset($this->products[$id])) {
            return $this->products[$id];
        }

        $attributes = $product->getAttributes();

        // Modèle partiel (select restreint) ou collections non chargées → requêtes.
        if (! array_key_exists('pko_not_discountable', $attributes) || ! $product->relationLoaded('collections')) {
            $this->loadProductTargets([$id]);

            return $this->products[$id] ?? self::emptyTargets();
        }

        return $this->products[$id] = [
            'brand' => isset($attributes['brand_id']) ? (int) $attributes['brand_id'] : null,
            'brand_family' => isset($attributes['pko_brand_family_id']) ? (int) $attributes['pko_brand_family_id'] : null,
            'not_discountable' => (bool) $attributes['pko_not_discountable'],
            'collections' => $product->collections->pluck('id')->map(fn ($v) => (int) $v)->values()->all(),
        ];
    }

    /** @param  list<int>  $ids */
    private function loadProductTargets(array $ids): void
    {
        $prefix = (string) config('lunar.database.table_prefix');

        $rows = DB::table($prefix.'products')->whereIn('id', $ids)->get();

        $collections = DB::table($prefix.'collection_product')
            ->whereIn('product_id', $ids)
            ->get(['product_id', 'collection_id'])
            ->groupBy('product_id');

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $this->products[$id] = [
                'brand' => isset($row->brand_id) ? (int) $row->brand_id : null,
                'brand_family' => isset($row->pko_brand_family_id) ? (int) $row->pko_brand_family_id : null,
                'not_discountable' => (bool) ($row->pko_not_discountable ?? false),
                'collections' => ($collections->get($id) ?? collect())
                    ->pluck('collection_id')->map(fn ($v) => (int) $v)->values()->all(),
            ];
        }
    }

    /** @return array{brand: ?int, brand_family: ?int, not_discountable: bool, collections: list<int>} */
    private static function emptyTargets(): array
    {
        return ['brand' => null, 'brand_family' => null, 'not_discountable' => false, 'collections' => []];
    }

    /** @param  list<int>  $customerIds */
    private static function customerKey(array $customerIds): string
    {
        $ids = array_map('intval', $customerIds);
        sort($ids);

        return implode(',', array_unique($ids));
    }
}
