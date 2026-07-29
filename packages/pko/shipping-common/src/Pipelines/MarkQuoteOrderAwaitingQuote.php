<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pipelines;

use Closure;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;

/**
 * Order-creation pipeline step: if any line contains a product with port mode 'quote',
 * override the order status to 'awaiting-quote' so the operator can send a
 * custom payment link before the client pays.
 *
 * Must be placed AFTER CreateOrderLines in config/lunar/orders.php pipelines.creation.
 *
 * Uses a whereIn subquery on ProductVariant IDs instead of traversing the MorphTo
 * relation — shipping lines have purchasable_type=ShippingOption (a DataType, not an
 * Eloquent model), which triggers ArgumentCountError when Eloquent tries to resolve
 * the polymorphic relation. whereHas on a MorphTo is also unsupported in Laravel.
 *
 * Le filtre sur purchasable_type passe par getMorphClass() : Lunar enregistre une
 * morph map (ModelManifest::morphMap()), donc la colonne contient l'alias
 * `product_variant` et jamais le FQCN — comparer à ProductVariant::class ne
 * matchait aucune ligne et le statut restait à `awaiting-payment`.
 */
final class MarkQuoteOrderAwaitingQuote
{
    public function handle(Order $order, Closure $next): Order
    {
        $quoteVariantIds = ProductVariant::whereHas(
            'product',
            fn ($q) => $q->where('pko_port_mode', 'quote'),
        )->pluck('id');

        $hasQuoteOnly = $quoteVariantIds->isNotEmpty() && $order->lines()
            ->where('purchasable_type', (new ProductVariant)->getMorphClass())
            ->whereIn('purchasable_id', $quoteVariantIds)
            ->exists();

        if ($hasQuoteOnly) {
            $order->forceFill(['status' => 'awaiting-quote'])->save();
        }

        return $next($order);
    }
}
