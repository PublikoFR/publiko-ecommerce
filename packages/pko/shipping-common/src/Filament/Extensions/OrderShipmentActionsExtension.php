<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Extensions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;
use Pko\ShippingCommon\Filament\Pages\DailyManifestPage;
use Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource;
use Pko\ShippingCommon\Filament\Support\CreateLabelActions;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Support\CarrierLabelUrl;

/**
 * Raccourcis d'expédition sur la fiche commande.
 *
 * La liste des envois pointait déjà vers la commande ; le chemin inverse manquait.
 * Depuis la commande on accède maintenant à l'étiquette, à la fiche de l'envoi et
 * au bordereau de remise du jour où le colis a été remis (le bordereau est par
 * nature journalier : il regroupe tous les colis d'une même remise au chauffeur,
 * pas ceux d'une seule commande).
 *
 * L'étiquette n'est jamais créée automatiquement : le bouton « Créer l'étiquette »
 * ouvre le groupe tant qu'un envoi reste sans étiquette.
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

        // En tête du groupe : c'est l'action attendue tant que l'étiquette manque.
        $items = CreateLabelActions::forHeader($order);

        // Point relais : lisible d'un coup d'œil, plutôt que d'aller le déchiffrer
        // dans le dump brut de `meta` du bloc « Informations supplémentaires ».
        if ($point = $this->pickupPoint($order)) {
            $items[] = Action::make('pickup_point')
                ->label(trim(sprintf(
                    'Point relais : %s%s',
                    $point['name'] ?? 'sans nom',
                    isset($point['id']) ? ' ('.$point['id'].')' : '',
                )))
                ->icon('heroicon-o-map-pin')
                ->disabled()
                ->tooltip(trim(sprintf(
                    '%s — %s %s',
                    $point['address1'] ?? '',
                    $point['postcode'] ?? '',
                    $point['city'] ?? '',
                )));
        }

        if ($shipments->isEmpty() && $items === []) {
            return $actions;
        }

        foreach ($shipments as $shipment) {
            $suffix = $shipments->count() > 1 ? ' — '.ucfirst((string) $shipment->carrier) : '';

            if ($labelUrl = CarrierLabelUrl::for($shipment)) {
                $items[] = Action::make("view_label_{$shipment->id}")
                    ->label('Voir l\'étiquette'.$suffix)
                    ->icon('heroicon-o-printer')
                    ->url($labelUrl, shouldOpenInNewTab: true);
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

    /**
     * @return array<string, mixed>|null
     */
    private function pickupPoint(Order $order): ?array
    {
        $meta = $order->meta instanceof \ArrayObject
            ? $order->meta->getArrayCopy()
            : (array) ($order->meta ?? []);

        $point = $meta['pickup_point'] ?? null;

        return is_array($point) && $point !== [] ? $point : null;
    }

    private function resolveOrder(): ?Order
    {
        return $this->caller?->record ?? null;
    }
}
