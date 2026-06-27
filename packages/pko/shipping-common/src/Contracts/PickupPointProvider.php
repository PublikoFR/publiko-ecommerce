<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Contracts;

use Pko\ShippingCommon\Dto\PickupPoint;

/**
 * Source de points relais proches d'un code postal.
 *
 * Implémentation V1 : ManualPickupPointProvider (retourne []), le front bascule
 * alors sur une saisie manuelle simplifiée. Un provider API (ex. SOAP Chronopost
 * « recherche point relais ») peut être lié à la place sans toucher au front.
 */
interface PickupPointProvider
{
    /**
     * Retourne les points relais proches du code postal donné.
     *
     * Doit être tolérant aux pannes : en cas d'erreur (réseau, API indisponible,
     * credentials absents), retourner [] plutôt que throw — le front propose alors
     * la saisie manuelle.
     *
     * @return list<PickupPoint>
     */
    public function search(string $postcode, string $countryCode = 'FR', ?string $serviceCode = null): array;
}
