<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

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

    /**
     * Weight of taxable lines only (excludes lines where the product has pko_free_shipping = true).
     */
    public static function fromCartTaxable(Cart $cart): float
    {
        $total = 0.0;

        foreach ($cart->lines as $line) {
            $variant = $line->purchasable;
            if ($variant?->product?->pko_free_shipping) {
                continue;
            }
            $total += self::variantWeightKg($variant) * (int) $line->quantity;
        }

        return round($total, 3);
    }

    /**
     * Returns true when every line in the cart is flagged pko_free_shipping.
     * An empty cart returns false (no lines → nothing is "all free").
     */
    public static function allLinesFreeShipping(Cart $cart): bool
    {
        $lines = $cart->lines;

        if ($lines->isEmpty()) {
            return false;
        }

        foreach ($lines as $line) {
            if (! $line->purchasable?->product?->pko_free_shipping) {
                return false;
            }
        }

        return true;
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
