<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Lunar\Admin\Support\Extending\ResourceExtension;

/**
 * Regroupe toutes les actions d'en-tête de la fiche commande dans un menu
 * déroulant unique. Doit être enregistrée APRÈS les autres extensions de
 * ManageOrder pour recevoir leurs actions.
 *
 * Les badges informatifs désactivés (nom `*_badge`) restent visibles hors du menu.
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

        foreach ($actions as $action) {
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
            return $badges;
        }

        return [
            ...$badges,
            ActionGroup::make($grouped)
                ->label('Actions')
                ->icon('heroicon-m-chevron-down')
                ->iconPosition('after')
                ->color('primary')
                ->button(),
        ];
    }
}
