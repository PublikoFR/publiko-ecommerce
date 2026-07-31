<?php

declare(strict_types=1);

namespace Pko\Storefront\Support;

use Lunar\Models\ProductVariant;

/**
 * Statut de disponibilité affiché au client, aligné sur ce que le panier accepte.
 *
 * Le mode d'achat Lunar (`ProductVariant::$purchasable`) fait foi :
 *   - `always`   → commandable même à stock zéro (stock fournisseur → « Sur commande ») ;
 *   - `in_stock` → commandable uniquement dans la limite du stock détenu.
 *
 * Sans cette distinction, la carte produit annonçait « Sur commande » pour toute
 * variante à stock zéro, y compris celles que `AddToCart` refuse ensuite.
 */
final class VariantAvailability
{
    /**
     * @return array{tone: string, label: string, orderable: bool}
     */
    public static function for(?ProductVariant $variant): array
    {
        if ($variant === null) {
            return ['tone' => 'neutral', 'label' => 'Indisponible', 'orderable' => false];
        }

        $stock = (int) $variant->stock;

        if ($stock > 5) {
            return ['tone' => 'success', 'label' => 'En stock', 'orderable' => true];
        }

        if ($stock > 0) {
            return ['tone' => 'warning', 'label' => 'Stock limité', 'orderable' => true];
        }

        if ($variant->canBeFulfilledAtQuantity(1)) {
            return ['tone' => 'neutral', 'label' => 'Sur commande', 'orderable' => true];
        }

        return ['tone' => 'neutral', 'label' => 'Épuisé', 'orderable' => false];
    }
}
