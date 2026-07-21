<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use App\Support\CustomerGroupDeletionImpact;
use App\Support\CustomerGroupGuard;
use Filament\Actions\DeleteAction as PageDeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Lunar\Admin\Support\Extending\ResourceExtension;

/**
 * Suppression d'un groupe client : cascade + confirmation éclairée.
 *
 * Toutes les FK vers `lunar_customer_groups` sont en NO ACTION → un delete natif
 * plante en 1451 dès qu'un groupe est référencé. On cascade donc explicitement
 * (clients réattribués au groupe par défaut, catalogue / réductions / livraison /
 * taxes détachés, tarifs supprimés), en annonçant l'impact dans la modale.
 *
 * Restent bloquants, faute de cascade possible :
 *   - le groupe par défaut Lunar ;
 *   - le groupe pro (config default_customer_group_handle) ;
 *   - les restrictions catalogue explicites (cf. CustomerGroupGuard).
 *
 * Quand un élément ne dépend QUE du groupe supprimé, l'utilisateur choisit de le
 * supprimer aussi ou de le conserver — le détacher en silence le rendrait inactif
 * sans prévenir (le scope Lunar est un `whereHas`).
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
                        $orphans = 0;
                        $blocked = [];

                        foreach ($records as $group) {
                            $reason = CustomerGroupGuard::blockReason($group);
                            if ($reason !== null) {
                                $blocked[] = ($group->name ?: $group->handle).' — '.$reason;

                                continue;
                            }
                            // Cascade complète. En masse on ne peut pas poser la
                            // question par groupe : on CONSERVE les éléments qui
                            // deviendraient orphelins (choix non destructif), et on
                            // le signale dans la notification.
                            $orphans += count(CustomerGroupDeletionImpact::analyse($group)['orphans']);
                            CustomerGroupDeletionImpact::apply($group, deleteOrphans: false);
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
                                ->body($orphans > 0
                                    ? $orphans.' élément(s) ne dépendaient que d’un groupe supprimé et ont été conservés — ils sont désormais inactifs.'
                                    : null)
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
                $action
                    ->modalDescription(fn ($record): string => self::describeImpact($record))
                    ->form(fn ($record): array => self::impactForm($record))
                    ->before(function ($record, array $data, PageDeleteAction $action): void {
                        $reason = CustomerGroupGuard::blockReason($record);
                        if ($reason !== null) {
                            Notification::make()
                                ->warning()
                                ->title('Suppression impossible')
                                ->body(($record->name ?: $record->handle).' — '.$reason)
                                ->persistent()
                                ->send();
                            $action->cancel();

                            return;
                        }

                        // Cascade complète (clients, catalogue, réductions, livraison,
                        // taxes, tarifs). Filament exécute le delete du groupe ensuite.
                        CustomerGroupDeletionImpact::prepare(
                            $record,
                            ($data['orphan_strategy'] ?? 'keep') === 'delete',
                        );
                    });
            }
        }

        return $actions;
    }

    /** Récapitulatif des conséquences, affiché au-dessus du formulaire de confirmation. */
    private static function describeImpact($record): string
    {
        $lines = CustomerGroupDeletionImpact::summarise(
            CustomerGroupDeletionImpact::analyse($record)
        );

        if ($lines === []) {
            return 'Cette action est irréversible.';
        }

        return "Cette action est irréversible.\n\n".implode("\n", array_map(
            fn (string $line): string => '• '.$line,
            $lines
        ));
    }

    /**
     * Choix explicite quand des éléments ne dépendent QUE de ce groupe.
     *
     * Sans ce choix, les détacher silencieusement les rendrait inactifs : le scope
     * Lunar est un `whereHas`, une réduction sans aucun groupe ne s'applique plus
     * à personne. On préfère poser la question plutôt que de décider à la place
     * de l'utilisateur.
     *
     * @return array<int, mixed>
     */
    private static function impactForm($record): array
    {
        $orphans = CustomerGroupDeletionImpact::analyse($record)['orphans'];

        if ($orphans === []) {
            return [];
        }

        $list = implode("\n", array_map(
            fn (array $o): string => "• {$o['label']} « {$o['name']} »",
            $orphans
        ));

        return [
            Placeholder::make('orphans_warning')
                ->label('Éléments rattachés à ce seul groupe')
                ->content(new HtmlString(
                    nl2br(e(
                        "Les éléments suivants ne dépendent que de ce groupe :\n{$list}\n\n".
                        'Conservés, ils resteront en base mais ne s’appliqueront plus à aucun client.'
                    ))
                )),
            Radio::make('orphan_strategy')
                ->label('Que faire de ces éléments ?')
                ->options([
                    'keep' => 'Conserver (ils deviendront inactifs)',
                    'delete' => 'Les supprimer également',
                ])
                ->default('keep')
                ->required(),
        ];
    }
}
