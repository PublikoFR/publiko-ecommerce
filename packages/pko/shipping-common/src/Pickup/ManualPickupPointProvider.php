<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pickup;

use Pko\ShippingCommon\Contracts\PickupPointProvider;

/**
 * Provider V1 par défaut : aucune source automatique de points relais.
 *
 * Retourne toujours [] → le front (ShippingOptions) bascule sur la saisie
 * manuelle simplifiée du point relais. Brancher un provider API (SOAP Chronopost
 * « recherche point relais ») via le container pour activer la liste automatique :
 *
 *   $this->app->bind(PickupPointProvider::class, ChronopostPickupPointProvider::class);
 */
final class ManualPickupPointProvider implements PickupPointProvider
{
    public function search(string $postcode, string $countryCode = 'FR', ?string $serviceCode = null): array
    {
        return [];
    }
}
