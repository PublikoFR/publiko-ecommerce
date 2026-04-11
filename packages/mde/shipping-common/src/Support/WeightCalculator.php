<?php

declare(strict_types=1);

namespace Mde\ShippingCommon\Support;

use InvalidArgumentException;
use Lunar\Models\Cart;
use Lunar\Models\Order;

final class WeightCalculator
{
    public static function fromCart(Cart $cart): float
    {
        $total = 0.0;

        foreach ($cart->lines as $line) {
            $variant = $line->purchasable;
            $total += self::variantWeightKg($variant) * (int) $line->quantity;
        }

        return round($total, 3);
    }

    public static function fromOrder(Order $order): float
    {
        $total = 0.0;

        foreach ($order->lines as $line) {
            $variant = $line->purchasable;
            if ($variant === null) {
                continue;
            }
            $total += self::variantWeightKg($variant) * (int) $line->quantity;
        }

        return round($total, 3);
    }

    private static function variantWeightKg(mixed $variant): float
    {
        if ($variant === null) {
            return 0.0;
        }

        $value = (float) ($variant->weight_value ?? 0);
        $unit = strtolower((string) ($variant->weight_unit ?? 'kg'));

        return match ($unit) {
            'kg' => $value,
            'g' => $value / 1000,
            'lb' => $value * 0.453592,
            default => throw new InvalidArgumentException("Unsupported weight unit '{$unit}' (variant id {$variant->id})."),
        };
    }
}
