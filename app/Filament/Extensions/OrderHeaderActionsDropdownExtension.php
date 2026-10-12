<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;
use Pko\Account\Support\OrderStatusLabel;

/**
 * Regroupe toutes les actions d'en-tête de la fiche commande dans un menu
 * déroulant unique. Doit être enregistrée APRÈS les autres extensions de
 * ManageOrder pour recevoir leurs actions.
 *
 * Les badges informatifs désactivés (nom `*_badge`) restent visibles hors du menu,
 * ainsi que le changement de statut (`update_status`), affiché à côté du menu avec
 * le statut courant : action la plus fréquente de la fiche, trop cachée dans le menu.
 */
final class OrderHeaderActionsDropdownExtension extends ResourceExtension
{
    /**
     * @param  array<int, Action|ActionGroup>  $actions
     * @return array<int, Action|ActionGroup>
     */
    public function headerActions(array $actions): array
    {
        $badges = [];
        $grouped = [];
        $status = null;

        foreach ($actions as $action) {
            if ($action instanceof Action && $action->getName() === 'update_status') {
                $status = $this->statusButton($action);

                continue;
            }

            if ($action instanceof Action && str_ends_with((string) $action->getName(), '_badge')) {
                $badges[] = $action;

                continue;
            }

            // Un sous-groupe est rendu comme une section du menu, pas comme un
            // menu déroulant imbriqué.
            if ($action instanceof ActionGroup) {
                $action->dropdown(false);
            }

            $grouped[] = $action;
        }

        if ($grouped === []) {
            return array_values(array_filter([...$badges, $status]));
        }

        return array_values(array_filter([
            ...$badges,
            $status,
            ActionGroup::make($grouped)
                ->label('Actions')
                ->icon('heroicon-m-chevron-down')
                ->iconPosition('after')
                ->color('primary')
                ->button(),
        ]));
    }

    private function statusButton(Action $action): Action
    {
        return $action
            ->label(fn (): string => 'Statut : '.OrderStatusLabel::of($this->resolveOrder()?->status))
            ->icon('heroicon-m-chevron-down')
            ->iconPosition('after')
            ->color('gray')
            ->button();
    }

    private function resolveOrder(): ?Order
    {
        $record = $this->caller?->record ?? null;

        return $record instanceof Order ? $record : null;
    }
}
