<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Pko\ShippingCommon\Tracking\LaPosteTrackingClient;

/**
 * Page publique de suivi d'un colis, selon le transporteur.
 *
 * Chronopost a sa propre page (doc Web Services VL3.25.10.10, §2.6.3.e) : la page
 * La Poste affichait bien le colis mais n'est pas celle que le client attend d'un
 * envoi Chronopost. Colissimo et tout transporteur inconnu restent sur La Poste.
 */
final class CarrierTrackingUrl
{
    public const CHRONOPOST = 'https://www.chronopost.fr/tracking-no-cms/suivi-page?langue=fr_FR&listeNumerosLT=';

    public static function for(?string $carrier, string $trackingNumber): string
    {
        $base = $carrier === 'chronopost'
            ? self::CHRONOPOST
            : LaPosteTrackingClient::PUBLIC_TRACKING_URL;

        return $base.rawurlencode($trackingNumber);
    }
}
