<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Console\Commands;

use Illuminate\Console\Command;
use Lunar\Models\Order;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Shipping\ShipmentLabelService;

/**
 * Enregistre les envois transporteur « en attente » manquants des commandes payées.
 *
 * `OrderShipmentObserver` ne réagit qu'à une transition de statut : une commande
 * importée, saisie en back-office ou reprise dans un état déjà payé n'a jamais
 * d'envoi. Aucune étiquette n'est créée ici — ni appel transporteur : l'admin la
 * crée ensuite depuis la fiche commande.
 *
 * Idempotent : les commandes portant déjà un envoi sont ignorées, et
 * l'enregistrement repose sur un `firstOrCreate`.
 */
class BackfillShipmentsCommand extends Command
{
    protected $signature = 'shipping:backfill-shipments
                            {--dry-run : Affiche ce qui serait créé sans rien écrire}
                            {--limit=100 : Nombre maximum de commandes traitées}';

    protected $description = 'Enregistre les envois transporteur en attente manquants sur les commandes payées';

    private const PAID_STATUSES = ['paid', 'payment-received', 'dispatched'];

    public function handle(ShipmentLabelService $labels): int
    {
        $orders = Order::query()
            ->whereIn('status', self::PAID_STATUSES)
            // Sur l'adresse de LIVRAISON uniquement : l'adresse de facturation n'a
            // jamais de `shipping_option`, un filtre global exclurait toute commande.
            ->whereHas('addresses', fn ($q) => $q->where('type', 'shipping')->whereNotNull('shipping_option'))
            ->with('shippingAddress')
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get()
            ->filter(fn (Order $order): bool => $this->needsShipment($order));

        if ($orders->isEmpty()) {
            $this->info('Aucune commande à rattraper.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($orders as $order) {
            [$carrier, $serviceCode] = explode('.', (string) $order->shippingAddress->shipping_option, 2);

            $this->line(sprintf(
                '%s commande #%d (%s) → %s / %s',
                $dryRun ? '[dry-run]' : 'Envoi en attente',
                $order->id,
                $order->reference,
                $carrier,
                $serviceCode,
            ));

            if (! $dryRun) {
                $labels->recordPending($order);
            }
        }

        $this->info(sprintf('%d commande(s) %s.', $orders->count(), $dryRun ? 'à rattraper' : 'rattrapée(s)'));

        return self::SUCCESS;
    }

    private function needsShipment(Order $order): bool
    {
        $option = $order->shippingAddress?->shipping_option;

        if (! is_string($option) || ! str_contains($option, '.')) {
            return false;
        }

        [$carrier] = explode('.', $option, 2);

        if (! in_array($carrier, ['chronopost', 'colissimo'], true)) {
            return false;
        }

        return CarrierShipment::query()->where('order_id', $order->id)->doesntExist();
    }
}
