<?php

declare(strict_types=1);

namespace App\Pipelines\Orders;

use Closure;
use Lunar\Models\Order;

/**
 * Order-creation pipeline step: copy the customer notes from cart.meta to the order column.
 *
 * Same flow as PropagateCartSiteNamePipeline: the checkout stores the value in
 * cart.meta['pko_customer_notes'] on blur (so it survives 3DS redirects), and it
 * lands here in a dedicated column shown on the back-office order page.
 *
 * Must run after FillOrderFromCart (which sets cart_id on the order).
 */
final class PropagateCartCustomerNotesPipeline
{
    public const MAX_LENGTH = 2000;

    public function handle(Order $order, Closure $next): Order
    {
        $notes = $order->cart?->meta['pko_customer_notes'] ?? null;

        if (filled($notes)) {
            $order->forceFill([
                'pko_customer_notes' => mb_substr((string) $notes, 0, self::MAX_LENGTH),
            ])->save();
        }

        return $next($order);
    }
}
