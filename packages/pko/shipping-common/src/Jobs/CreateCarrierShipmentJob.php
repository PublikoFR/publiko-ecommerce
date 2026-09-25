<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Order;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Dto\ShipmentRequest;
use Pko\ShippingCommon\Mail\ShipmentCreatedMail;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Support\CarrierProductCodeResolver;
use Pko\ShippingCommon\Support\ParcelDimensionsCalculator;
use Pko\ShippingCommon\Support\WeightCalculator;
use RuntimeException;
use Throwable;

class CreateCarrierShipmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600, 14400];

    public function __construct(
        public readonly int $orderId,
        public readonly string $carrier,
        public readonly string $serviceCode,
        public readonly string $origin = CarrierShipment::ORIGIN_WEKLO,
    ) {}

    public function handle(): void
    {
        $order = Order::query()->findOrFail($this->orderId);

        $shipment = CarrierShipment::query()->firstOrCreate(
            [
                'order_id' => $order->id,
                'carrier' => $this->carrier,
                'origin' => $this->origin,
            ],
            [
                'service_code' => $this->serviceCode,
                'status' => CarrierShipment::STATUS_PENDING,
            ],
        );

        $client = $this->resolveClient();

        $shippingAddress = $order->shippingAddress;
        if ($shippingAddress === null) {
            throw new RuntimeException("Order {$order->id} has no shipping address.");
        }

        $shipperConfig = config("{$this->carrier}.shipper");
        if (! is_array($shipperConfig)) {
            throw new RuntimeException("Missing shipper config for carrier {$this->carrier}.");
        }

        // Lunar casts `meta` as AsArrayObject — is_array() returns false on hydrated models.
        $meta = $order->meta instanceof \ArrayObject ? $order->meta->getArrayCopy() : (array) ($order->meta ?? []);
        $pickupPoint = is_array($meta['pickup_point'] ?? null) ? $meta['pickup_point'] : null;
        $pickupPointId = $pickupPoint !== null ? (string) ($pickupPoint['id'] ?? '') : '';
        $pickupPointId = $pickupPointId !== '' ? $pickupPointId : null;

        $recipient = [
            'name' => trim(($shippingAddress->first_name ?? '').' '.($shippingAddress->last_name ?? '')),
            'company' => $shippingAddress->company_name,
            'street' => $shippingAddress->line_one,
            'zip' => $shippingAddress->postcode,
            'city' => $shippingAddress->city,
            'country' => $shippingAddress->country?->iso2 ?? 'FR',
            'phone' => $shippingAddress->contact_phone,
            'email' => $shippingAddress->contact_email ?? $order->customer?->email,
        ];

        $request = new ShipmentRequest(
            orderId: $order->id,
            orderReference: (string) $order->reference,
            weightKg: WeightCalculator::fromOrder($order),
            serviceCode: $this->serviceCode,
            recipient: $this->applyPickupPoint($recipient, $pickupPoint),
            shipper: $shipperConfig,
            pickupPointId: $pickupPointId,
            carrierProductCode: app(CarrierProductCodeResolver::class)->resolve($this->carrier, $this->serviceCode),
            dimensionsCm: ParcelDimensionsCalculator::fromOrder($order, $this->carrier),
        );

        $shipment->payload_sent = (array) $request;
        $shipment->save();

        $response = $client->createShipment($request);

        $labelPath = "labels/{$order->id}/{$this->carrier}-{$response->trackingNumber}.pdf";
        Storage::disk('local')->put($labelPath, base64_decode($response->labelPdfBase64, true) ?: '');

        $shipment->fill([
            'tracking_number' => $response->trackingNumber,
            'label_path' => $labelPath,
            'status' => CarrierShipment::STATUS_CREATED,
            'response_received' => $response->rawResponse,
            'error_message' => null,
        ])->save();

        $this->markOrderDispatched($order);
        $this->notifyCustomer($order, $shipment);
    }

    /**
     * Livraison en point relais : le destinataire de la LT est le point relais, pas le client.
     *
     * Le point est désigné par son identifiant (`ShipmentRequest::$pickupPointId`, transmis
     * par Chronopost dans `refValue.idRelais`) ET par son adresse, comme dans l'exemple
     * officiel « Chrono RELAIS 13H » : nom du point en raison sociale, rue/CP/ville du
     * point. On conserve le nom, le téléphone et l'e-mail du client pour que le point
     * relais et les notifications identifient bien le destinataire final.
     *
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>|null  $pickupPoint
     * @return array<string, mixed>
     */
    protected function applyPickupPoint(array $recipient, ?array $pickupPoint): array
    {
        if ($pickupPoint === null || ($pickupPoint['address1'] ?? '') === '') {
            return $recipient;
        }

        return array_merge($recipient, [
            'company' => (string) ($pickupPoint['name'] ?? $recipient['company'] ?? ''),
            'street' => (string) $pickupPoint['address1'],
            'zip' => (string) ($pickupPoint['postcode'] ?? $recipient['zip'] ?? ''),
            'city' => (string) ($pickupPoint['city'] ?? $recipient['city'] ?? ''),
            'country' => (string) ($pickupPoint['country_code'] ?? $recipient['country'] ?? 'FR'),
        ]);
    }

    protected function markOrderDispatched(Order $order): void
    {
        try {
            if ($order->status !== 'dispatched' && $order->status !== 'delivered') {
                $order->forceFill(['status' => 'dispatched'])->save();
            }
        } catch (Throwable $e) {
            Log::warning('Failed to flip Order.status to dispatched', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyCustomer(Order $order, CarrierShipment $shipment): void
    {
        if ($shipment->notified_customer_at !== null) {
            return;
        }

        $recipient = $order->shippingAddress?->contact_email
            ?? $order->billingAddress?->contact_email
            ?? $order->customer?->email;

        if (empty($recipient)) {
            Log::info('Skipping shipment notification: no recipient email', [
                'order_id' => $order->id,
                'carrier_shipment_id' => $shipment->id,
            ]);

            return;
        }

        $mail = new ShipmentCreatedMail($shipment);

        // Le contenu de l'e-mail est éditable en back-office et peut y être
        // désactivé : on ne marque alors pas l'expédition comme notifiée, pour
        // que la réactivation du modèle reprenne les envois en attente.
        if (! $mail->shouldSend()) {
            Log::info('Shipment notification disabled by mail template', [
                'order_id' => $order->id,
                'carrier_shipment_id' => $shipment->id,
            ]);

            return;
        }

        try {
            Mail::to($recipient)->send($mail);

            $shipment->forceFill(['notified_customer_at' => now()])->save();
        } catch (Throwable $e) {
            Log::warning('Failed to send shipment email', [
                'order_id' => $order->id,
                'carrier_shipment_id' => $shipment->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        $shipment = CarrierShipment::query()
            ->where('order_id', $this->orderId)
            ->where('carrier', $this->carrier)
            ->where('origin', $this->origin)
            ->first();

        if ($shipment === null) {
            return;
        }

        $shipment->fill([
            'status' => CarrierShipment::STATUS_FAILED,
            'error_message' => $e->getMessage(),
        ])->save();
    }

    private function resolveClient(): CarrierClient
    {
        return app("pko.shipping.carrier.{$this->carrier}");
    }
}
