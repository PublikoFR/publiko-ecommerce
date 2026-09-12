<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;

/**
 * Affiche le nom du chantier (pko_site_name) sur la fiche commande Filament.
 *
 * ResourceExtension n'expose pas de hook infolist : on utilise un badge
 * Action désactivé dans headerActions, conformément au pattern OrderSplitBadgeExtension.
 */
final class OrderSiteNameExtension extends ResourceExtension
{
    /**
     * @param  array<int, Action|ActionGroup>  $actions
     * @return array<int, Action|ActionGroup>
     */
    public function headerActions(array $actions): array
    {
        $order = $this->resolveOrder();

        if ($order === null || ! filled($order->pko_site_name)) {
            return $actions;
        }

        $actions[] = Action::make('site_name_badge')
            ->label('Chantier : '.$order->pko_site_name)
            ->icon('heroicon-o-building-office-2')
            ->color('gray')
            ->disabled()
            ->button()
            ->tooltip('Nom du chantier renseigné par le client à la commande.');

        return $actions;
    }

    private function resolveOrder(): ?Order
    {
        return $this->caller?->record ?? null;
    }
}
