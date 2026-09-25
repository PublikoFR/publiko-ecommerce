<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Pko\ShippingCommon\Models\CarrierShipment;

/**
 * Lien signé et temporaire vers l'étiquette PDF d'un envoi.
 *
 * Par défaut le PDF s'ouvre dans le navigateur ; `download` le télécharge. Le
 * paramètre est signé avec le reste de l'URL : impossible de le basculer après coup.
 */
final class CarrierLabelUrl
{
    /** Même durée que les liens PDF Pennylane : la fiche commande peut rester ouverte. */
    private const TTL_HOURS = 12;

    public static function for(CarrierShipment $shipment, bool $download = false): ?string
    {
        if (! self::exists($shipment)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'pko.shipping.label.pdf',
            now()->addHours(self::TTL_HOURS),
            ['shipment' => $shipment->id, ...($download ? ['download' => 1] : [])],
        );
    }

    public static function exists(CarrierShipment $shipment): bool
    {
        return is_string($shipment->label_path)
            && $shipment->label_path !== ''
            && Storage::disk('local')->exists($shipment->label_path);
    }
}
