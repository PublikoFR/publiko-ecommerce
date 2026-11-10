<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Contracts;

use Lunar\Models\Contracts\Cart;

/**
 * Seuil de franco PERSONNEL d'un client (ex. avantage fidélité « franco
 * abaissé »). shipping-common ne connaît pas l'origine de l'avantage : un
 * package tiers lie ce contrat dans le conteneur (cf. pko/loyalty).
 *
 * Le seuil effectif est le plus bas entre le seuil global et ce seuil
 * personnel (`ShippingSettings::thresholdCentsForCart()`).
 */
interface CustomerFrancoThresholdProvider
{
    /**
     * Seuil personnel en cents HT pour le client du panier, ou null si aucun.
     */
    public function thresholdCentsFor(Cart $cart): ?int;
}
