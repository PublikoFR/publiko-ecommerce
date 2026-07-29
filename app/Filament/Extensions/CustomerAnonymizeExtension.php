<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Actions\AnonymizeCustomer;

/**
 * Remplace la suppression physique brute des clients (qui échoue en FK dès
 * qu'il y a une commande liée, cf. lunar_orders NO ACTION) par un traitement
 * RGPD « intelligent » :
 *
 * - client SANS commande → suppression physique complète (la fiche disparaît) ;
 * - client AVEC commande(s) → anonymisation (données perso effacées, commandes
 *   conservées pour la comptabilité) + masquage de la liste par défaut.
 *
 * Un filtre permet de réafficher les fiches anonymisées.
 */
class CustomerAnonymizeExtension extends ResourceExtension
{
    public function extendTable(Table $table): Table
    {
        // Action de ligne : traitement RGPD d'un client unique. Elle doit atterrir
        // DANS le dropdown d'actions de la ligne (PkoCustomerResource), pas à côté :
        // pushActions() l'ajouterait en frère de l'ActionGroup → bouton hors menu.
        $purge = Action::make('rgpd_purge')
            ->label('Supprimer (RGPD)')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer ce client ?')
            ->modalDescription(fn (Customer $record): string => $record->orders()->exists()
                ? 'Ce client a des commandes : il sera anonymisé (données personnelles effacées, commandes conservées pour la comptabilité) puis masqué de la liste. Action irréversible.'
                : "Ce client n'a aucune commande : il sera définitivement supprimé (fiche, comptes de connexion, adresses, listes…). Action irréversible.")
            ->modalSubmitActionLabel('Confirmer')
            ->action(function (Customer $record): void {
                $result = app(AnonymizeCustomer::class)->purge($record);

                Notification::make()
                    ->success()
                    ->title($result === 'deleted' ? 'Client supprimé' : 'Client anonymisé')
                    ->body($result === 'deleted'
                        ? 'La fiche et les données associées ont été définitivement supprimées.'
                        : 'Données personnelles effacées, commandes conservées, fiche masquée de la liste.')
                    ->send();
            });

        $injected = false;

        $rowActions = array_map(function ($action) use ($purge, &$injected) {
            if (! $injected && $action instanceof ActionGroup) {
                $injected = true;

                return $action->actions([...$action->getActions(), $purge]);
            }

            return $action;
        }, $table->getActions());

        // Aucun dropdown existant (ex. resource Lunar non swappée) → on retombe
        // sur une action de ligne autonome.
        if (! $injected) {
            $rowActions[] = $purge;
        }

        return $table
            ->actions($rowActions)
            // Coché par défaut → masque les fiches anonymisées. Décocher pour les revoir.
            ->pushFilters([
                Filter::make('hide_anonymized')
                    ->label('Masquer les clients anonymisés')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereNull('anonymized_at')),
            ])
            // Action groupée : remplace le bulk delete par défaut (qui échoue en FK).
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('rgpd_purge')
                        ->label('Supprimer (RGPD)')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Supprimer les clients sélectionnés ?')
                        ->modalDescription('Chaque client sans commande sera définitivement supprimé ; ceux ayant des commandes seront anonymisés (commandes conservées) et masqués de la liste. Action irréversible.')
                        ->modalSubmitActionLabel('Confirmer')
                        ->action(function (Collection $records): void {
                            $service = app(AnonymizeCustomer::class);
                            $deleted = 0;
                            $anonymized = 0;

                            foreach ($records as $record) {
                                if ($service->purge($record) === 'deleted') {
                                    $deleted++;
                                } else {
                                    $anonymized++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Traitement RGPD terminé')
                                ->body("{$deleted} client(s) supprimé(s), {$anonymized} anonymisé(s).")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
