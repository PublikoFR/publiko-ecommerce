<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Support;

use Filament\Actions\Action as PageAction;
use Filament\Actions\MountableAction;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Notifications\Notification;
use Lunar\Models\Order;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Shipping\ShipmentLabelService;
use Pko\ShippingCommon\Support\CarrierDisplayLabel;
use Throwable;

/**
 * Boutons « Créer l'étiquette » de la fiche commande.
 *
 * Deux emplacements, même comportement : le menu « Actions » de l'en-tête
 * (action de page) et la section « Livraison » (action d'infolist). Un bouton
 * par origine de stock dont l'étiquette reste à créer.
 */
final class CreateLabelActions
{
    /**
     * @return list<PageAction>
     */
    public static function forHeader(Order $order): array
    {
        return self::build($order, fn (string $name): PageAction => PageAction::make($name));
    }

    /**
     * @return list<InfolistAction>
     */
    public static function forInfolist(Order $order): array
    {
        return self::build($order, fn (string $name): InfolistAction => InfolistAction::make($name)->button());
    }

    /**
     * @template T of MountableAction
     *
     * @param  callable(string): T  $make
     * @return list<T>
     */
    private static function build(Order $order, callable $make): array
    {
        $service = app(ShipmentLabelService::class);
        $option = $service->carrierOption($order);

        if ($option === null) {
            return [];
        }

        $origins = $service->originsAwaitingLabel($order);
        $carrierLabel = CarrierDisplayLabel::carrier($option[0]);
        $actions = [];

        foreach ($origins as $origin) {
            $retry = CarrierShipment::query()
                ->where('order_id', $order->id)
                ->where('carrier', $option[0])
                ->where('origin', $origin)
                ->where('status', CarrierShipment::STATUS_FAILED)
                ->exists();

            $label = ($retry ? 'Recréer l\'étiquette ' : 'Créer l\'étiquette ').$carrierLabel
                .(count($origins) > 1 ? ' — '.self::originLabel($origin) : '');

            $actions[] = $make("create_label_{$origin}")
                ->label($label)
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading($label)
                ->modalDescription(self::confirmation($origin, $carrierLabel))
                ->modalSubmitActionLabel('Créer l\'étiquette')
                ->action(fn () => self::run($order, $origin));
        }

        return $actions;
    }

    private static function run(Order $order, string $origin): void
    {
        try {
            $shipment = app(ShipmentLabelService::class)->createLabel($order, $origin);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Étiquette non créée')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Étiquette créée')
            ->body('N° de suivi : '.$shipment->tracking_number)
            ->success()
            ->send();
    }

    private static function confirmation(string $origin, string $carrierLabel): string
    {
        $text = "L'envoi est déclaré chez {$carrierLabel} et l'étiquette PDF est générée. "
            .'Le client reçoit l\'e-mail d\'expédition avec son numéro de suivi.';

        return $origin === CarrierShipment::ORIGIN_SUPPLIER_VIA_WEKLO
            ? $text.' À faire une fois la marchandise du fournisseur arrivée à l\'entrepôt.'
            : $text;
    }

    private static function originLabel(string $origin): string
    {
        return $origin === CarrierShipment::ORIGIN_SUPPLIER_VIA_WEKLO
            ? 'marchandise fournisseur'
            : 'stock';
    }
}
