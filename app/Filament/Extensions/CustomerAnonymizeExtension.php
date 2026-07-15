<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Pko\CustomerAuth\Actions\AnonymizeCustomer;

/**
 * Remplace la suppression physique des clients (qui échoue en FK dès qu'il y a
 * une commande / un compte lié, cf. lunar_orders NO ACTION) par une
 * anonymisation RGPD : les données perso sont effacées, les comptes de
 * connexion supprimés, mais la fiche client et ses commandes sont conservées
 * pour la comptabilité.
 */
class CustomerAnonymizeExtension extends ResourceExtension
{
    public function extendTable(Table $table): Table
    {
        return $table->bulkActions([
            BulkActionGroup::make([
                BulkAction::make('anonymize')
                    ->label('Anonymiser (RGPD)')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anonymiser les clients sélectionnés ?')
                    ->modalDescription('Les données personnelles seront effacées et les comptes de connexion supprimés. Les commandes sont conservées (anonymisées) pour la comptabilité. Cette action est irréversible.')
                    ->modalSubmitActionLabel('Anonymiser')
                    ->action(function (Collection $records): void {
                        $anonymizer = app(AnonymizeCustomer::class);

                        foreach ($records as $record) {
                            $anonymizer->handle($record);
                        }

                        Notification::make()
                            ->success()
                            ->title($records->count().' client(s) anonymisé(s)')
                            ->body('Données personnelles effacées et comptes de connexion supprimés. Commandes conservées.')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]),
        ]);
    }
}
