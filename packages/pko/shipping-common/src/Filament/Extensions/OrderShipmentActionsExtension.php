<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Extensions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\Storage;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;
use Pko\ShippingCommon\Filament\Pages\DailyManifestPage;
use Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource;
use Pko\ShippingCommon\Models\CarrierShipment;

/**
 * Raccourcis d'expédition sur la fiche commande.
 *
 * La liste des envois pointait déjà vers la commande ; le chemin inverse manquait.
 * Depuis la commande on accède maintenant à l'étiquette, à la fiche de l'envoi et
 * au bordereau de remise du jour où le colis a été remis (le bordereau est par
 * nature journalier : il regroupe tous les colis d'une même remise au chauffeur,
 * pas ceux d'une seule commande).
 */
final class OrderShipmentActionsExtension extends ResourceExtension
{
    /**
     * @param  array<int, Action|ActionGroup>  $actions
     * @return array<int, Action|ActionGroup>
     */
    public function headerActions(array $actions): array
    {
        $order = $this->resolveOrder();

        if (! $order) {
            return $actions;
        }

        $shipments = CarrierShipment::query()
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->get();

        if ($shipments->isEmpty()) {
            return $actions;
        }

        $items = [];

        foreach ($shipments as $shipment) {
            $suffix = $shipments->count() > 1 ? ' — '.ucfirst((string) $shipment->carrier) : '';

            if ($this->hasLabel($shipment)) {
                $items[] = Action::make("download_label_{$shipment->id}")
                    ->label('Télécharger l\'étiquette'.$suffix)
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => response()->streamDownload(
                        fn () => print (Storage::disk('local')->get($shipment->label_path)),
                        basename((string) $shipment->label_path),
                        ['Content-Type' => 'application/pdf'],
                    ));
            }

            $items[] = Action::make("view_shipment_{$shipment->id}")
                ->label($shipment->tracking_number
                    ? "Envoi n° {$shipment->tracking_number}"
                    : 'Envoi transporteur'.$suffix)
                ->icon('heroicon-o-truck')
                ->url(CarrierShipmentResource::getUrl('view', ['record' => $shipment->id]));
        }

        // Date de remise = date de création de la première étiquette de la commande.
        $manifestDate = $shipments
            ->firstWhere('status', CarrierShipment::STATUS_CREATED)
            ?->created_at;

        if ($manifestDate !== null) {
            $items[] = Action::make('daily_manifest')
                ->label('Bordereau du '.$manifestDate->format('d/m/Y'))
                ->icon('heroicon-o-clipboard-document-check')
                ->url(DailyManifestPage::getUrl().'?date='.$manifestDate->format('Y-m-d'));
        }

        $actions[] = ActionGroup::make($items)
            ->label('Expédition')
            ->icon('heroicon-o-truck')
            ->button();

        return $actions;
    }

    private function hasLabel(CarrierShipment $shipment): bool
    {
        return is_string($shipment->label_path)
            && $shipment->label_path !== ''
            && Storage::disk('local')->exists($shipment->label_path);
    }

    private function resolveOrder(): ?Order
    {
        return $this->caller?->record ?? null;
    }
}
