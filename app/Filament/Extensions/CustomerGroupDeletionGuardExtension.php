<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use App\Support\CustomerGroupGuard;
use Filament\Actions\DeleteAction as PageDeleteAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Lunar\Admin\Support\Extending\ResourceExtension;

/**
 * Garde-fou de suppression des groupes clients. Toutes les FK vers
 * `lunar_customer_groups` (customers, collections, prices, products, shipping,
 * tax, discounts) sont en NO ACTION → un delete natif plante en 1451 dès qu'un
 * groupe est référencé. On bloque proprement (message FR) :
 *   - le groupe par défaut Lunar ;
 *   - le groupe pro (config default_customer_group_handle) ;
 *   - tout groupe encore référencé.
 * Un groupe custom sans aucune référence reste supprimable.
 *
 * Enregistré à la fois sur CustomerGroupResource (extendTable → bulk delete de la
 * liste) et sur EditCustomerGroup (headerActions → delete de la page d'édition).
 */
class CustomerGroupDeletionGuardExtension extends ResourceExtension
{
    /** Bulk delete de la liste. */
    public function extendTable(Table $table): Table
    {
        return $table->bulkActions([
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label('Supprimer')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer les groupes clients sélectionnés ?')
                    ->action(function (Collection $records): void {
                        $deleted = 0;
                        $blocked = [];

                        foreach ($records as $group) {
                            $reason = CustomerGroupGuard::blockReason($group);
                            if ($reason !== null) {
                                $blocked[] = ($group->name ?: $group->handle).' — '.$reason;

                                continue;
                            }
                            $group->delete();
                            $deleted++;
                        }

                        if ($blocked !== []) {
                            Notification::make()
                                ->warning()
                                ->title($deleted.' supprimé(s), '.count($blocked).' conservé(s)')
                                ->body(implode("\n", $blocked))
                                ->persistent()
                                ->send();
                        } else {
                            Notification::make()
                                ->success()
                                ->title($deleted.' groupe(s) supprimé(s)')
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ]),
        ]);
    }

    /**
     * Delete de la page d'édition : on remplace le garde-fou Lunar (qui ne
     * vérifie que les clients) par un contrôle complet.
     *
     * @param  array<int, mixed>  $actions
     * @return array<int, mixed>
     */
    public function headerActions(array $actions): array
    {
        foreach ($actions as $action) {
            if ($action instanceof PageDeleteAction) {
                $action->before(function ($record, PageDeleteAction $action): void {
                    $reason = CustomerGroupGuard::blockReason($record);
                    if ($reason !== null) {
                        Notification::make()
                            ->warning()
                            ->title('Suppression impossible')
                            ->body(($record->name ?: $record->handle).' — '.$reason)
                            ->persistent()
                            ->send();
                        $action->cancel();
                    }
                });
            }
        }

        return $actions;
    }
}
