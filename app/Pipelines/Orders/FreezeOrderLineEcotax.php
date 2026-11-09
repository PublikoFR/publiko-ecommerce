<?php

declare(strict_types=1);

namespace App\Pipelines\Orders;

use Closure;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;

/**
 * Order-creation pipeline step : fige l'éco-participation unitaire (cents HT)
 * de chaque ligne produit dans `OrderLine.meta['ecotax_cents']`, en conservant
 * les meta existantes. Lu ensuite par `App\Support\Ecotax::unitForOrderLine()`,
 * qui ne relit JAMAIS la variante (une commande passée ne change pas).
 *
 * Placement dans `config/lunar/orders.php` → `pipelines.creation` : APRÈS
 * `CleanUpOrderLines`. Ce dernier compare les meta des lignes de commande à
 * celles des lignes de panier (signature md5) et supprime toute ligne dont les
 * meta diffèrent : figer avant lui ferait supprimer les lignes avec éco-taxe.
 * Et AVANT `MarkQuoteOrderAwaitingQuote`, qui doit rester le dernier pipe.
 *
 * Les variantes sont résolues par une requête unique sur leurs IDs (filtre
 * `purchasable_type` = alias morph) plutôt que via la relation MorphTo : les
 * lignes shipping ont un purchasable DataType non-Eloquent.
 */
final class FreezeOrderLineEcotax
{
    public function handle(Order $order, Closure $next): mixed
    {
        $lines = $order->lines()
            ->where('purchasable_type', (new ProductVariant)->getMorphClass())
            ->get();

        if ($lines->isEmpty()) {
            return $next($order);
        }

        $ecotaxByVariant = ProductVariant::query()
            ->whereIn('id', $lines->pluck('purchasable_id')->unique()->all())
            ->where('pko_ecotax_cents', '>', 0)
            ->pluck('pko_ecotax_cents', 'id');

        /** @var OrderLine $line */
        foreach ($lines as $line) {
            $unit = (int) ($ecotaxByVariant[$line->purchasable_id] ?? 0);
            if ($unit <= 0) {
                continue;
            }

            $meta = $line->meta instanceof \ArrayObject
                ? $line->meta->getArrayCopy()
                : (array) ($line->meta ?? []);
            $meta['ecotax_cents'] = $unit;

            $line->meta = $meta;
            $line->save();
        }

        $order->unsetRelation('lines');

        return $next($order);
    }
}
