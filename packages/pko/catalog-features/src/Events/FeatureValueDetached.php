<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Lunar\Models\Product;
use Pko\CatalogFeatures\Models\FeatureValue;

class FeatureValueDetached
{
    use Dispatchable;

    public function __construct(
        public readonly Product $product,
        public readonly FeatureValue $value,
    ) {}
}
