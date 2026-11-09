<?php

declare(strict_types=1);

namespace App\Support;

use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;

/**
 * Éco-participation (modèle « dont », cf. docs/ecotax.md).
 *
 * Le prix de vente en base INCLUT l'éco-taxe : ces montants ne s'ajoutent
 * jamais à un total, ils servent uniquement à afficher « dont éco-participation
 * X € ». Tous les montants sont en cents HT (la TVA du produit s'y applique).
 *
 * - Panier : lu sur la variante courante (`pko_ecotax_cents`).
 * - Commande : lu UNIQUEMENT sur la valeur figée `OrderLine.meta['ecotax_cents']`
 *   (posée par le pipe FreezeOrderLineEcotax) — le montant d'une commande
 *   passée ne doit jamais changer si l'éco-taxe de la variante évolue.
 */
final class Ecotax
{
    /** Éco-taxe unitaire (cents HT) d'un purchasable ; 0 si absente ou pas une variante. */
    public static function forPurchasable(mixed $purchasable): int
    {
        if (! $purchasable instanceof ProductVariant) {
            return 0;
        }

        return max(0, (int) ($purchasable->pko_ecotax_cents ?? 0));
    }

    /** Éco-taxe totale d'une ligne de panier (unitaire × quantité). */
    public static function forCartLine(CartLine $line): int
    {
        return self::forPurchasable($line->purchasable) * max(0, (int) $line->quantity);
    }

    /** Éco-taxe unitaire FIGÉE d'une ligne de commande ; 0 si non figée. */
    public static function unitForOrderLine(OrderLine $line): int
    {
        $meta = $line->meta;
        if ($meta instanceof \ArrayObject) {
            $meta = $meta->getArrayCopy();
        }

        $value = is_array($meta) ? ($meta['ecotax_cents'] ?? null) : null;

        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /** Éco-taxe totale figée d'une ligne de commande (unitaire × quantité). */
    public static function forOrderLine(OrderLine $line): int
    {
        return self::unitForOrderLine($line) * max(0, (int) $line->quantity);
    }

    /** Somme des éco-taxes des lignes produit du panier. */
    public static function forCart(Cart $cart): int
    {
        return (int) $cart->lines->sum(static fn (CartLine $line): int => self::forCartLine($line));
    }

    /** Somme des éco-taxes figées des lignes produit de la commande (hors shipping). */
    public static function forOrder(Order $order): int
    {
        return (int) $order->lines
            ->filter(static fn (OrderLine $line): bool => $line->type !== 'shipping')
            ->sum(static fn (OrderLine $line): int => self::forOrderLine($line));
    }
}
