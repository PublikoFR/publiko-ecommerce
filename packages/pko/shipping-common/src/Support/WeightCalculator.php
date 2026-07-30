<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Illuminate\Support\Collection;
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
     * Weight of an arbitrary pre-filtered collection of lines.
     *
     * Used by ShippingCalculator which partitions lines before calling this.
     *
     * @param  Collection<int, object>  $lines
     */
    public static function fromLines(Collection $lines): float
    {
        $total = 0.0;

        foreach ($lines as $line) {
            $total += self::variantWeightKg($line->purchasable) * (int) $line->quantity;
        }

        return round($total, 3);
    }

    /**
     * Weight of taxable lines only (excludes lines where the effective port mode is 'free').
     */
    public static function fromCartTaxable(Cart $cart): float
    {
        $total = 0.0;

        foreach ($cart->lines as $line) {
            $variant = $line->purchasable;
            $product = $variant?->product;
            if ($product !== null && PortModeResolver::resolve($product) === 'free') {
                continue;
            }
            $total += self::variantWeightKg($variant) * (int) $line->quantity;
        }

        return round($total, 3);
    }

    /**
     * Returns true when every line in the cart has an effective port mode of 'free'.
     * An empty cart returns false (no lines → nothing is "all free").
     */
    public static function allLinesFreeShipping(Cart $cart): bool
    {
        $lines = $cart->lines;

        if ($lines->isEmpty()) {
            return false;
        }

        foreach ($lines as $line) {
            $product = $line->purchasable?->product;
            if ($product === null || PortModeResolver::resolve($product) !== 'free') {
                return false;
            }
        }

        return true;
    }

    /**
     * Sum of sub-total HT (cents, ex-VAT) for lines eligible for franco de port.
     *
     * Eligible = pko_franco_eligible is true AND effective port mode is not 'quote'.
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
     * Sum of sub-total HT (cents, ex-VAT) for ALL cart lines (basis = cart_total).
     */
    public static function cartSubtotalHt(Cart $cart): int
    {
        $total = 0;

        foreach ($cart->lines as $line) {
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

        $resolvedMode = PortModeResolver::resolve($product);

        // Pour le mode 'inherit', l'éligibilité franco est dérivée du mode résolu :
        // le fournisseur peut changer son port_inclus à tout moment, donc on ne lit pas
        // pko_franco_eligible (il était de toute façon écrasé à false par l'UI — bug L3).
        if ((string) ($product->pko_port_mode ?? '') === 'inherit') {
            return $resolvedMode === 'standard';
        }

        return $product->pko_franco_eligible === true
            && $resolvedMode !== 'quote';
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
