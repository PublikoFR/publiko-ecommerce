<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Pko\ShippingCommon\Models\CarrierShipment;

/**
 * Lien signé et temporaire vers l'étiquette PDF d'un envoi (ouverture dans le navigateur).
 */
final class CarrierLabelUrl
{
    /** Même durée que les liens PDF Pennylane : la fiche commande peut rester ouverte. */
    private const TTL_HOURS = 12;

    public static function for(CarrierShipment $shipment): ?string
    {
        if (! self::exists($shipment)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'pko.shipping.label.pdf',
            now()->addHours(self::TTL_HOURS),
            ['shipment' => $shipment->id],
        );
    }

    public static function exists(CarrierShipment $shipment): bool
    {
        return is_string($shipment->label_path)
            && $shipment->label_path !== ''
            && Storage::disk('local')->exists($shipment->label_path);
    }
}
