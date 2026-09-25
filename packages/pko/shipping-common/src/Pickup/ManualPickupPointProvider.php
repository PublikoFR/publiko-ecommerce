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
    public function search(
        string $postcode,
        string $countryCode = 'FR',
        ?string $serviceCode = null,
        ?string $city = null,
        ?int $weightGrams = null,
    ): array
    {
        return [];
    }

    /**
     * Aucune source branchée : ce n'est pas « zéro point relais autour de ce code
     * postal », c'est « la recherche automatique n'existe pas ». Le front doit
     * pouvoir le dire au client plutôt que d'afficher une liste vide.
     */
    public function lastSearchError(): ?string
    {
        return 'no_provider_configured';
    }
}
