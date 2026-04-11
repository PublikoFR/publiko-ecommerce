<?php

declare(strict_types=1);

namespace Mde\ShippingCommon\Observers;

use Lunar\Models\Order;
use Mde\ShippingCommon\Jobs\CreateCarrierShipmentJob;

class OrderShipmentObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('payment_status')) {
            return;
        }

        if ($order->payment_status !== 'paid') {
            return;
        }

        $option = $order->shippingAddress?->shipping_option;
        if (! is_string($option) || ! str_contains($option, '.')) {
            return;
        }

        [$carrier, $serviceCode] = explode('.', $option, 2);

        if (! in_array($carrier, ['chronopost', 'colissimo'], true)) {
            return;
        }

        CreateCarrierShipmentJob::dispatch($order->id, $carrier, $serviceCode);
    }
}
