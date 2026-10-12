<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Shipping;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Order;
use Pko\ShippingCommon\Mail\ShipmentCreatedMail;
use Pko\ShippingCommon\Models\CarrierShipment;
use Throwable;

/**
 * Cycle de vie d'une commande entre l'étiquette et le transporteur.
 *
 * - Étiquette créée → « Commande préparée » : le colis attend le transporteur.
 * - Colis remis au transporteur → « Expédié » : action « Remis au transporteur »
 *   du bordereau, premier scan du suivi, ou changement de statut manuel.
 *
 * L'e-mail « Commande expédiée » part au passage en « Expédié », pas à la création
 * de l'étiquette : un colis qui attend son enlèvement n'est pas expédié.
 */
class OrderHandover
{
    public const PREPARED = 'prepared';

    public const DISPATCHED = 'dispatched';

    public const DELIVERED = 'delivered';

    public function markPrepared(Order $order): void
    {
        if (in_array($order->status, [self::PREPARED, self::DISPATCHED, self::DELIVERED], true)) {
            return;
        }

        $this->setStatus($order, self::PREPARED);
    }

    /**
     * @return bool true si la commande vient de passer en « Expédié »
     */
    public function markDispatched(Order $order): bool
    {
        if (in_array($order->status, [self::DISPATCHED, self::DELIVERED], true)) {
            return false;
        }

        return $this->setStatus($order, self::DISPATCHED);
    }

    /**
     * Prévient le client pour chaque colis étiqueté pas encore notifié.
     * Appelé au passage en « Expédié », quel qu'en soit le déclencheur.
     */
    public function notifyCustomer(Order $order): void
    {
        $shipments = CarrierShipment::query()
            ->where('order_id', $order->id)
            ->where('status', CarrierShipment::STATUS_CREATED)
            ->whereNotNull('tracking_number')
            ->whereNull('notified_customer_at')
            ->get();

        foreach ($shipments as $shipment) {
            $this->notifyShipment($order, $shipment);
        }
    }

    private function notifyShipment(Order $order, CarrierShipment $shipment): void
    {
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

    private function setStatus(Order $order, string $status): bool
    {
        try {
            $order->forceFill(['status' => $status])->save();

            return true;
        } catch (Throwable $e) {
            Log::warning('Failed to flip Order.status', [
                'order_id' => $order->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
