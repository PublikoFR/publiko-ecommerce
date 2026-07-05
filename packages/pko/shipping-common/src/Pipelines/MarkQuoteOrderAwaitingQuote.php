<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pipelines;

use Closure;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;

/**
 * Order-creation pipeline step: if any line contains a pko_quote_only product,
 * override the order status to 'awaiting-quote' so the operator can send a
 * custom payment link before the client pays.
 *
 * Must be placed AFTER CreateOrderLines in config/lunar/orders.php pipelines.creation.
 *
 * Uses a whereIn subquery on ProductVariant IDs instead of traversing the MorphTo
 * relation — shipping lines have purchasable_type=ShippingOption (a DataType, not an
 * Eloquent model), which triggers ArgumentCountError when Eloquent tries to resolve
 * the polymorphic relation. whereHas on a MorphTo is also unsupported in Laravel.
 */
final class MarkQuoteOrderAwaitingQuote
{
    public function handle(Order $order, Closure $next): Order
    {
        $quoteVariantIds = ProductVariant::whereHas(
            'product',
            fn ($q) => $q->where('pko_quote_only', true),
        )->pluck('id');

        $hasQuoteOnly = $quoteVariantIds->isNotEmpty() && $order->lines()
            ->where('purchasable_type', ProductVariant::class)
            ->whereIn('purchasable_id', $quoteVariantIds)
            ->exists();

        if ($hasQuoteOnly) {
            $order->forceFill(['status' => 'awaiting-quote'])->save();
        }

        return $next($order);
    }
}
