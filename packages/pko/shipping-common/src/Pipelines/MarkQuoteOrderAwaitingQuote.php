<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pipelines;

use Closure;
use Lunar\Models\Order;

/**
 * Order-creation pipeline step: if any line contains a pko_quote_only product,
 * override the order status to 'awaiting-quote' so the operator can send a
 * custom payment link before the client pays.
 *
 * Must be placed AFTER CreateOrderLines in config/lunar/orders.php pipelines.creation.
 */
final class MarkQuoteOrderAwaitingQuote
{
    public function handle(Order $order, Closure $next): Order
    {
        $lines = $order->lines()->with(['purchasable.product'])->get();

        $hasQuoteOnly = $lines->contains(
            fn ($line) => (bool) ($line->purchasable?->product?->pko_quote_only ?? false),
        );

        if ($hasQuoteOnly) {
            $order->forceFill(['status' => 'awaiting-quote'])->save();
        }

        return $next($order);
    }
}
