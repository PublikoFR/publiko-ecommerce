<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Lunar\Models\Product;

class ProductFeaturesSynced
{
    use Dispatchable;

    /**
     * @param  array<int>  $attached  feature value ids added
     * @param  array<int>  $detached  feature value ids removed
     */
    public function __construct(
        public readonly Product $product,
        public readonly array $attached,
        public readonly array $detached,
    ) {}
}
