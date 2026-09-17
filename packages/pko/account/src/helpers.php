<?php

declare(strict_types=1);

use Pko\Account\Support\OrderStatusLabel;
use Pko\Account\Support\PaymentDisplayLabel;

if (! function_exists('order_status_label')) {
    /**
     * Libellé FR d'un statut de commande Lunar (config lunar.orders.statuses).
     * Fallback : slug inchangé si aucun libellé n'existe.
     */
    function order_status_label(?string $status): string
    {
        return OrderStatusLabel::of($status);
    }
}

if (! function_exists('payment_driver_label')) {
    function payment_driver_label(?string $driver): string
    {
        return PaymentDisplayLabel::driver($driver);
    }
}

if (! function_exists('payment_status_label')) {
    function payment_status_label(?string $status): string
    {
        return PaymentDisplayLabel::status($status);
    }
}
