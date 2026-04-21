<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Tracking\LaPosteTrackingClient;

class ShipmentCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly CarrierShipment $shipment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre commande '.$this->orderReference().' a été expédiée',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'pko-shipping-common::emails.shipment-created',
            with: [
                'shipment' => $this->shipment,
                'trackingUrl' => $this->trackingUrl(),
                'carrierLabel' => $this->carrierLabel(),
                'orderReference' => $this->orderReference(),
                'brandName' => brand_name(),
            ],
        );
    }

    protected function trackingUrl(): string
    {
        return LaPosteTrackingClient::PUBLIC_TRACKING_URL.$this->shipment->tracking_number;
    }

    protected function carrierLabel(): string
    {
        return ucfirst((string) $this->shipment->carrier);
    }

    protected function orderReference(): string
    {
        return (string) ($this->shipment->order?->reference ?? $this->shipment->order_id);
    }
}
