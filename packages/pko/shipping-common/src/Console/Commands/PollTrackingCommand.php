<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Tracking\LaPosteTrackingClient;
use Pko\ShippingCommon\Tracking\TrackingException;
use Pko\ShippingCommon\Tracking\TrackingStatus;
use Throwable;

class PollTrackingCommand extends Command
{
    protected $signature = 'shipping:poll-tracking
        {--limit=100 : Max shipments to process per run}
        {--min-age=1 : Skip shipments last polled less than N hours ago}';

    protected $description = 'Poll La Poste tracking API for non-terminal shipments and update delivery status + Order.status on delivery.';

    public function handle(LaPosteTrackingClient $client): int
    {
        $limit = (int) $this->option('limit');
        $minAgeHours = (int) $this->option('min-age');
        $recentlyPolledCutoff = Carbon::now()->subHours(max(0, $minAgeHours));

        $query = CarrierShipment::query()
            ->where('status', CarrierShipment::STATUS_CREATED)
            ->whereNotNull('tracking_number')
            ->where(function ($q): void {
                $q->whereNull('delivery_status')
                    ->orWhereNotIn('delivery_status', CarrierShipment::DELIVERY_TERMINAL_STATUSES);
            })
            ->where(function ($q) use ($recentlyPolledCutoff): void {
                $q->whereNull('delivery_status_updated_at')
                    ->orWhere('delivery_status_updated_at', '<=', $recentlyPolledCutoff);
            })
            ->orderBy('created_at')
            ->limit($limit);

        $count = 0;
        $updated = 0;
        $delivered = 0;
        $failed = 0;

        foreach ($query->cursor() as $shipment) {
            $count++;
            try {
                $status = $client->track((string) $shipment->tracking_number);
            } catch (TrackingException $e) {
                $failed++;
                Log::channel($this->logChannel())->warning('tracking poll failed', [
                    'carrier_shipment_id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'error' => $e->getMessage(),
                ]);

                continue;
            } catch (Throwable $e) {
                $failed++;
                Log::channel($this->logChannel())->error('tracking poll unexpected error', [
                    'carrier_shipment_id' => $shipment->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($this->applyStatus($shipment, $status)) {
                $updated++;
                if ($status->isDelivered()) {
                    $delivered++;
                }
            }
        }

        $this->info("Polled {$count} shipments — {$updated} updated, {$delivered} newly delivered, {$failed} errors.");

        return self::SUCCESS;
    }

    protected function applyStatus(CarrierShipment $shipment, TrackingStatus $status): bool
    {
        $previous = (string) ($shipment->delivery_status ?? '');

        $shipment->forceFill([
            'delivery_status' => $status->status,
            'delivery_status_updated_at' => now(),
            'delivered_at' => $status->deliveredAt ?? ($status->isDelivered() ? now() : $shipment->delivered_at),
            'tracking_events' => $status->events,
        ])->save();

        if ($previous !== $status->status && $status->isDelivered()) {
            $this->markOrderDelivered($shipment);

            return true;
        }

        return $previous !== $status->status;
    }

    protected function markOrderDelivered(CarrierShipment $shipment): void
    {
        try {
            $order = Order::query()->find($shipment->order_id);
            if ($order === null || $order->status === 'delivered') {
                return;
            }
            $order->forceFill(['status' => 'delivered'])->save();
        } catch (Throwable $e) {
            Log::channel($this->logChannel())->warning('Failed to flip Order.status to delivered', [
                'order_id' => $shipment->order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function logChannel(): string
    {
        return config('logging.channels.shipping-quickcost') ? 'shipping-quickcost' : 'stack';
    }
}
