<?php

declare(strict_types=1);

namespace App\Observers;

/** Visibilité catalogue des produits. Cf. CatalogAvailabilityObserver. */
class ProductAvailabilityObserver extends CatalogAvailabilityObserver
{
    protected function table(): string
    {
        return 'lunar_customer_group_product';
    }
}
