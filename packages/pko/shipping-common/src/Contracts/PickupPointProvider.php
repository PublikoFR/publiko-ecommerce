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

    /**
     * Motif technique du dernier échec de search(), ou null si la dernière
     * recherche s'est déroulée normalement (y compris avec zéro résultat).
     *
     * search() étant volontairement tolérant aux pannes, un tableau vide est
     * ambigu : « aucun point relais autour de ce code postal » et « le service
     * est injoignable » se ressemblent côté appelant. Sans cette distinction le
     * front affichait le même écran muet dans les deux cas — le bouton
     * « Rechercher » semblait ne rien faire. Destiné au log et au choix du
     * message utilisateur, jamais à être affiché tel quel au client.
     */
    public function lastSearchError(): ?string;
}
