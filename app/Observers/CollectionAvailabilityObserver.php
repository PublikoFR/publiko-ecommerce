<?php

declare(strict_types=1);

namespace App\Observers;

/** Visibilité catalogue des collections. Cf. CatalogAvailabilityObserver. */
class CollectionAvailabilityObserver extends CatalogAvailabilityObserver
{
    protected function table(): string
    {
        return 'lunar_collection_customer_group';
    }
}
