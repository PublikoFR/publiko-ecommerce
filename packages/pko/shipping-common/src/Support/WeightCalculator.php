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

    /**
     * Sum of sub-total HT (cents, ex-VAT) for lines eligible for franco de port.
     *
     * Eligible = pko_franco_eligible is true AND pko_logistics_class !== 'C' AND pko_quote_only is false.
     */
    public static function francoEligibleSubtotalHt(Cart $cart): int
    {
        $total = 0;

        foreach ($cart->lines as $line) {
            $product = $line->purchasable?->product;
            if (! self::isFrancoEligible($product)) {
                continue;
            }
            $total += (int) ($line->subTotal?->value ?? 0);
        }

        return $total;
    }

    /**
     * Returns true when at least one cart line is NOT eligible for franco de port.
     */
    public static function cartHasFrancoExcludedLine(Cart $cart): bool
    {
        foreach ($cart->lines as $line) {
            $product = $line->purchasable?->product;
            if (! self::isFrancoEligible($product)) {
                return true;
            }
        }

        return false;
    }

    private static function isFrancoEligible(mixed $product): bool
    {
        if ($product === null) {
            return false;
        }

        return $product->pko_franco_eligible === true
            && $product->pko_logistics_class !== 'C'
            && $product->pko_quote_only === false;
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
