<?php

declare(strict_types=1);

namespace App\Pipelines\Orders;

use Closure;
use Lunar\Models\Order;

/**
 * Order-creation pipeline step: copy pko_site_name from cart.meta to the order column.
 *
 * The value is stored in cart.meta['pko_site_name'] during checkout and propagated
 * here so the column is queryable/sortable in back-office without going through JSON.
 *
 * Must run after FillOrderFromCart (which sets cart_id on the order) so that
 * $order->cart is resolvable.
 */
final class PropagateCartSiteNamePipeline
{
    public function handle(Order $order, Closure $next): Order
    {
        $cart = $order->cart;

        if ($cart === null) {
            return $next($order);
        }

        $siteName = $cart->meta['pko_site_name'] ?? null;

        if (filled($siteName)) {
            $order->forceFill(['pko_site_name' => mb_substr((string) $siteName, 0, 255)])->save();
        }

        return $next($order);
    }
}
