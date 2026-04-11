<?php

declare(strict_types=1);

namespace Mde\ShippingCommon\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Order;
use Mde\ShippingCommon\Contracts\CarrierClient;
use Mde\ShippingCommon\Dto\ShipmentRequest;
use Mde\ShippingCommon\Models\CarrierShipment;
use Mde\ShippingCommon\Support\WeightCalculator;
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
    ) {}

    public function handle(): void
    {
        $order = Order::query()->findOrFail($this->orderId);

        $shipment = CarrierShipment::query()->firstOrCreate(
            [
                'order_id' => $order->id,
                'carrier' => $this->carrier,
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

        $request = new ShipmentRequest(
            orderId: $order->id,
            orderReference: (string) $order->reference,
            weightKg: WeightCalculator::fromOrder($order),
            serviceCode: $this->serviceCode,
            recipient: [
                'name' => trim(($shippingAddress->first_name ?? '').' '.($shippingAddress->last_name ?? '')),
                'company' => $shippingAddress->company_name,
                'street' => $shippingAddress->line_one,
                'zip' => $shippingAddress->postcode,
                'city' => $shippingAddress->city,
                'country' => $shippingAddress->country?->iso2 ?? 'FR',
                'phone' => $shippingAddress->contact_phone,
                'email' => $shippingAddress->contact_email ?? $order->customer?->email,
            ],
            shipper: $shipperConfig,
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
    }

    public function failed(Throwable $e): void
    {
        $shipment = CarrierShipment::query()
            ->where('order_id', $this->orderId)
            ->where('carrier', $this->carrier)
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
        return app("mde.shipping.carrier.{$this->carrier}");
    }
}
