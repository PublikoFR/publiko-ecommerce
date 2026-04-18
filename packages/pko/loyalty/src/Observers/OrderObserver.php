<?php

declare(strict_types=1);

namespace Pko\Loyalty\Observers;

use Lunar\Models\Order;
use Pko\Loyalty\Services\LoyaltyManager;

class OrderObserver
{
    public function __construct(protected LoyaltyManager $manager) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('placed_at')) {
            return;
        }

        if ($order->placed_at === null) {
            return;
        }

        $this->manager->awardForOrder($order);
    }

    public function created(Order $order): void
    {
        if ($order->placed_at !== null) {
            $this->manager->awardForOrder($order);
        }
    }
}
