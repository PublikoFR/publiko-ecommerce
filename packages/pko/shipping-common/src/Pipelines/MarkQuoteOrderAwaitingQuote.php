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
 * Only ProductVariant lines are checked — shipping lines use ShippingOption
 * (a DataType, not an Eloquent model) as purchasable_type and cannot be resolved
 * via MorphTo without an ArgumentCountError.
 */
final class MarkQuoteOrderAwaitingQuote
{
    public function handle(Order $order, Closure $next): Order
    {
        $hasQuoteOnly = $order->lines()
            ->where('purchasable_type', ProductVariant::class)
            ->whereHas('purchasable', fn ($q) => $q->whereHas(
                'product',
                fn ($q) => $q->where('pko_quote_only', true),
            ))
            ->exists();

        if ($hasQuoteOnly) {
            $order->forceFill(['status' => 'awaiting-quote'])->save();
        }

        return $next($order);
    }
}
