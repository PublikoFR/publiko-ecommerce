<?php

declare(strict_types=1);

namespace Mde\ShippingCommon\Support;

final class ZoneResolver
{
    public static function isMetropole(string $postcode, string $country = 'FR'): bool
    {
        if (strtoupper($country) !== 'FR') {
            return false;
        }

        $postcode = preg_replace('/\s+/', '', $postcode) ?? '';

        if (! preg_match('/^\d{5}$/', $postcode)) {
            return false;
        }

        if (str_starts_with($postcode, '20')) {
            return false;
        }

        $prefix = (int) substr($postcode, 0, 3);
        if ($prefix >= 971 && $prefix <= 978) {
            return false;
        }

        return true;
    }
}
