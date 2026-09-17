<?php

declare(strict_types=1);

namespace Pko\Account\Support;

/**
 * Libellés FR des drivers et statuts de transaction de paiement.
 *
 * Les slugs (stripe, succeeded, processing…) restent la valeur stockée.
 */
final class PaymentDisplayLabel
{
    public static function driver(?string $driver): string
    {
        return self::translate('account::payments.drivers.', $driver);
    }

    public static function status(?string $status): string
    {
        if ($status === null || $status === '') {
            return '—';
        }

        $orderLabel = OrderStatusLabel::of($status);
        if ($orderLabel !== $status) {
            return $orderLabel;
        }

        return self::translate('account::payments.statuses.', $status);
    }

    private static function translate(string $prefix, ?string $slug): string
    {
        if ($slug === null || $slug === '') {
            return '—';
        }

        $key = $prefix.$slug;
        if (trans()->has($key)) {
            return (string) __($key);
        }

        return $slug;
    }
}
