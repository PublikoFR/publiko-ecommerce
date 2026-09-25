<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Shipping;

use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Pko\ShippingCommon\Jobs\CreateCarrierShipmentJob;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Models\Supplier;
use Throwable;

/**
 * Envois transporteur d'une commande : planification et création d'étiquette.
 *
 * L'étiquette n'est jamais créée automatiquement. À l'encaissement, on enregistre
 * seulement les envois « en attente » (un par origine de stock) ; c'est l'admin
 * qui crée l'étiquette depuis la fiche commande, au moment où le colis est prêt.
 * Une étiquette créée d'office partait avant la préparation — voire avant la
 * réception de la marchandise fournisseur — et déclenchait l'e-mail d'expédition.
 */
final class ShipmentLabelService
{
    public const CARRIERS = ['chronopost', 'colissimo'];

    /**
     * Origines dont l'étiquette est à notre nom. `supplier_direct` : le fournisseur
     * expédie lui-même, Weklo n'édite rien.
     */
    public const LABELLED_ORIGINS = [
        CarrierShipment::ORIGIN_WEKLO,
        CarrierShipment::ORIGIN_SUPPLIER_VIA_WEKLO,
    ];

    public function __construct(
        private readonly ShipmentSplitter $splitter,
    ) {}

    /**
     * Transporteur et service choisis au checkout (`chronopost.chrono13`).
     *
     * @return array{0: string, 1: string}|null
     */
    public function carrierOption(Order $order): ?array
    {
        $option = $order->shippingAddress?->shipping_option;

        if (! is_string($option) || ! str_contains($option, '.')) {
            return null;
        }

        [$carrier, $serviceCode] = explode('.', $option, 2);

        return in_array($carrier, self::CARRIERS, true) && $serviceCode !== ''
            ? [$carrier, $serviceCode]
            : null;
    }

    /**
     * Origines de stock présentes dans la commande (un envoi par origine).
     *
     * @return list<string>
     */
    public function origins(Order $order): array
    {
        // productLines() exclut la ligne shipping, dont le purchasable est un
        // value-object (ShippingOption) : l'eager-load MorphTo planterait.
        $lines = $order->productLines()->with(['purchasable.product'])->get();

        $supplierIds = $lines
            ->map(fn ($line) => $line->purchasable?->product?->pko_supplier_id ?? null)
            ->filter()
            ->unique()
            ->values();

        $suppliers = $supplierIds->isNotEmpty()
            ? Supplier::query()->whereIn('id', $supplierIds)->get()->keyBy('id')
            : collect();

        return array_values(array_map(
            fn (ShipmentGroup $group): string => $group->origin,
            $this->splitter->split($lines, $suppliers),
        ));
    }

    /**
     * Enregistre les envois en attente, sans appeler le transporteur. Idempotent.
     *
     * @return int nombre d'envois créés
     */
    public function recordPending(Order $order): int
    {
        $option = $this->carrierOption($order);

        if ($option === null) {
            return 0;
        }

        [$carrier, $serviceCode] = $option;
        $created = 0;

        foreach ($this->origins($order) as $origin) {
            $shipment = CarrierShipment::query()->firstOrCreate(
                ['order_id' => $order->id, 'carrier' => $carrier, 'origin' => $origin],
                ['service_code' => $serviceCode, 'status' => CarrierShipment::STATUS_PENDING],
            );

            $created += $shipment->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /**
     * Origines dont l'étiquette reste à créer (aucune, en attente ou en échec).
     *
     * Calculé sans écrire en base : appelé au rendu de la fiche commande.
     *
     * @return list<string>
     */
    public function originsAwaitingLabel(Order $order): array
    {
        $option = $this->carrierOption($order);

        if ($option === null) {
            return [];
        }

        $done = CarrierShipment::query()
            ->where('order_id', $order->id)
            ->where('carrier', $option[0])
            ->where('status', CarrierShipment::STATUS_CREATED)
            ->pluck('origin')
            ->all();

        return array_values(array_filter(
            $this->origins($order),
            fn (string $origin): bool => in_array($origin, self::LABELLED_ORIGINS, true)
                && ! in_array($origin, $done, true),
        ));
    }

    /**
     * Crée l'étiquette immédiatement (appel transporteur synchrone).
     *
     * En cas d'échec l'envoi passe « en échec » avec le motif, puis l'exception
     * est relancée pour que l'appelant l'affiche.
     *
     * @throws Throwable
     */
    public function createLabel(Order $order, string $origin): CarrierShipment
    {
        $option = $this->carrierOption($order);

        if ($option === null) {
            throw new \RuntimeException('Cette commande n\'a pas de livraison transporteur.');
        }

        if (! in_array($origin, self::LABELLED_ORIGINS, true)) {
            throw new \RuntimeException('Cet envoi est expédié directement par le fournisseur : aucune étiquette à créer.');
        }

        [$carrier, $serviceCode] = $option;
        $job = new CreateCarrierShipmentJob($order->id, $carrier, $serviceCode, $origin);

        try {
            $job->handle();
        } catch (Throwable $e) {
            $job->failed($e);

            Log::warning('Carrier label creation failed', [
                'order_id' => $order->id,
                'carrier' => $carrier,
                'origin' => $origin,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return CarrierShipment::query()
            ->where('order_id', $order->id)
            ->where('carrier', $carrier)
            ->where('origin', $origin)
            ->firstOrFail();
    }
}
