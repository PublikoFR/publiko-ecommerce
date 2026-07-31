<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Console\Commands;

use Illuminate\Console\Command;
use Lunar\Models\Order;
use Pko\ShippingCommon\Jobs\CreateCarrierShipmentJob;
use Pko\ShippingCommon\Models\CarrierShipment;

/**
 * Crée les envois transporteur manquants pour les commandes déjà payées.
 *
 * `OrderShipmentObserver` ne réagit qu'à une transition de statut : une commande
 * importée, saisie en back-office ou reprise dans un état déjà payé n'a jamais
 * d'étiquette. Même chose pour toute commande passée pendant que le worker de
 * queue était arrêté et dont le job a été perdu (purge de la file, par exemple).
 *
 * Idempotent : les commandes portant déjà un envoi sont ignorées, et le job
 * lui-même repose sur un `firstOrCreate`.
 */
class BackfillShipmentsCommand extends Command
{
    protected $signature = 'shipping:backfill-shipments
                            {--dry-run : Affiche ce qui serait créé sans rien dispatcher}
                            {--limit=100 : Nombre maximum de commandes traitées}';

    protected $description = 'Crée les envois transporteur manquants sur les commandes payées';

    private const PAID_STATUSES = ['paid', 'payment-received', 'dispatched'];

    public function handle(): int
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
                $dryRun ? '[dry-run]' : 'Dispatch',
                $order->id,
                $order->reference,
                $carrier,
                $serviceCode,
            ));

            if (! $dryRun) {
                CreateCarrierShipmentJob::dispatch($order->id, $carrier, $serviceCode);
            }
        }

        $this->info(sprintf('%d commande(s) %s.', $orders->count(), $dryRun ? 'à rattraper' : 'mises en file'));

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
