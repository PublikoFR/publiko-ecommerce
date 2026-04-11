<?php

declare(strict_types=1);

namespace Mde\CatalogFeatures\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Lunar\Models\Product;
use Mde\CatalogFeatures\Models\FeatureValue;

class FeatureValueDetached
{
    use Dispatchable;

    public function __construct(
        public readonly Product $product,
        public readonly FeatureValue $value,
    ) {}
}
