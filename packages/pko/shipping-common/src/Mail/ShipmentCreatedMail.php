<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Tracking\LaPosteTrackingClient;

/**
 * E-mail 06 « Commande expédiée ».
 *
 * La classe ne porte plus que la traduction du domaine (expédition) en
 * placeholders : le texte vit dans `pko_mail_templates`, éditable en back-office.
 */
class ShipmentCreatedMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly CarrierShipment $shipment)
    {
        parent::__construct('order.shipped', [
            'first_name' => self::firstName($shipment),
            'order_reference' => (string) ($shipment->order?->reference ?? $shipment->order_id),
            'tracking_url' => LaPosteTrackingClient::PUBLIC_TRACKING_URL.$shipment->tracking_number,
            'carrier_name' => ucfirst((string) $shipment->carrier),
        ]);
    }

    private static function firstName(CarrierShipment $shipment): string
    {
        $order = $shipment->order;

        return (string) (
            $order?->shippingAddress?->first_name
            ?? $order?->billingAddress?->first_name
            ?? $order?->customer?->first_name
            ?? ''
        );
    }
}
