<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Pko\ShippingCommon\Models\CarrierService;
use Throwable;

/**
 * Libellés FR des transporteurs, services et statuts d'envoi.
 * Les slugs (chronopost, pending, chrono13…) restent la valeur stockée.
 */
final class CarrierDisplayLabel
{
    /** @var array<string, string> */
    private static array $serviceCache = [];

    public static function carrier(?string $code): string
    {
        return self::translate('pko-shipping-common::admin.carrier.', $code);
    }

    public static function status(?string $status): string
    {
        return self::translate('pko-shipping-common::admin.shipment_status.', $status);
    }

    public static function service(?string $carrier, ?string $serviceCode): string
    {
        if ($serviceCode === null || $serviceCode === '') {
            return '—';
        }

        $cacheKey = ($carrier ?? '').'|'.$serviceCode;
        if (array_key_exists($cacheKey, self::$serviceCache)) {
            return self::$serviceCache[$cacheKey];
        }

        $label = null;
        try {
            $query = CarrierService::query()->where('service_code', $serviceCode);
            if (is_string($carrier) && $carrier !== '') {
                $query->where('carrier_code', $carrier);
            }
            $found = $query->value('label');
            $label = is_string($found) && $found !== '' ? $found : null;
        } catch (Throwable) {
            $label = null;
        }

        return self::$serviceCache[$cacheKey] = $label ?? $serviceCode;
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
