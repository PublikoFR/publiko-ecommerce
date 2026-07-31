<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Pko\ShippingCommon\Models\CarrierShipment;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bordereau récapitulatif de remise transporteur (PDF).
 *
 * Construit uniquement à partir de nos données locales : le bordereau est une
 * preuve de prise en charge signée entre l'expéditeur et le chauffeur, il ne
 * transite par aucun web service.
 */
final class ManifestPdf
{
    /**
     * @param  Collection<int, CarrierShipment>  $shipments
     */
    public static function download(Collection $shipments, Carbon $date): Response
    {
        $rows = $shipments
            ->sortBy('created_at')
            ->map(fn (CarrierShipment $shipment): array => self::row($shipment))
            ->values()
            ->all();

        $national = collect($rows)->where('country', 'FR')->count();

        $pdf = Pdf::loadView('pko-shipping-common::pdf.manifest', [
            'date' => $date,
            'rows' => $rows,
            'shipper' => self::shipper($shipments),
            'national' => $national,
            'international' => count($rows) - $national,
            'total' => count($rows),
            'brandName' => brand_name(),
        ])->setPaper('a4');

        return $pdf->download('bordereau-'.$date->format('Y-m-d').'.pdf');
    }

    /**
     * @return array<string, string>
     */
    private static function row(CarrierShipment $shipment): array
    {
        $payload = $shipment->payload_sent?->toArray() ?? [];
        $recipient = is_array($payload['recipient'] ?? null) ? $payload['recipient'] : [];
        $address = $shipment->order?->shippingAddress;

        return [
            'tracking_number' => (string) $shipment->tracking_number,
            'account' => (string) config("{$shipment->carrier}.credentials.account", ''),
            'carrier' => (string) $shipment->carrier,
            'product' => (string) ($payload['carrierProductCode'] ?? $shipment->service_code),
            'service' => (string) $shipment->service_code,
            'zip' => (string) ($recipient['zip'] ?? $address?->postcode ?? ''),
            'city' => (string) ($recipient['city'] ?? $address?->city ?? ''),
            'country' => (string) ($recipient['country'] ?? $address?->country?->iso2 ?? 'FR'),
            'order' => '#'.(string) $shipment->order_id,
        ];
    }

    /**
     * Coordonnées de l'expéditeur, prises sur le transporteur majoritaire de la
     * sélection — la config `shipper` est identique d'un transporteur à l'autre
     * (mêmes variables SHIPPER_*), le choix ne fait que garantir une clé existante.
     *
     * @param  Collection<int, CarrierShipment>  $shipments
     * @return array<string, string>
     */
    private static function shipper(Collection $shipments): array
    {
        $carrier = (string) ($shipments->first()?->carrier ?? 'chronopost');
        $config = config("{$carrier}.shipper", []);

        return [
            'name' => (string) ($config['name'] ?? brand_name()),
            'street' => (string) ($config['street'] ?? ''),
            'zip' => (string) ($config['zip'] ?? ''),
            'city' => (string) ($config['city'] ?? ''),
            'country' => (string) ($config['country'] ?? 'FR'),
            'phone' => (string) ($config['phone'] ?? ''),
            'email' => (string) ($config['email'] ?? ''),
        ];
    }
}
