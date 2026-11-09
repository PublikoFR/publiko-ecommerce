<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Extensions;

use Filament\Actions\Action as PageAction;
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
use Pko\CustomerAuth\Actions\ValidateCustomerAccount;

/**
 * Validation manuelle des comptes pro (FEAT-006) : action « Valider le compte »
 * sur la liste (ligne + groupée), filtre « En attente de validation », et la même
 * action en en-tête de la fiche (vue et édition).
 *
 * Enregistrée sur PkoCustomerResource (table) ET sur PkoViewCustomer /
 * PkoEditCustomer (hook Lunar `headerActions`). À déclarer APRÈS
 * CustomerAnonymizeExtension, qui remplace les actions groupées.
 */
class CustomerValidationExtension extends ResourceExtension
{
    private const LABEL = 'Valider le compte';

    private const ICON = 'heroicon-o-check-badge';

    private const CONFIRMATION = 'Le client pourra se connecter à son espace pro et recevra un e-mail lui annonçant que son compte est validé.';

    public function extendTable(Table $table): Table
    {
        $validate = Action::make('validate_account')
            ->label(self::LABEL)
            ->icon(self::ICON)
            ->color('success')
            ->visible(fn (Customer $record): bool => $record->getAttribute('pko_status') === 'pending')
            ->disabled(fn (Customer $record): bool => ValidateCustomerAccount::blockReason($record) !== null)
            ->tooltip(fn (Customer $record): ?string => ValidateCustomerAccount::blockReason($record))
            ->requiresConfirmation()
            ->modalHeading('Valider ce compte ?')
            ->modalDescription(self::CONFIRMATION)
            ->modalSubmitActionLabel('Valider')
            ->action(fn (Customer $record) => self::validate($record));

        // Dans le dropdown de la ligne (cf. CustomerAnonymizeExtension).
        $injected = false;
        $rowActions = array_map(function ($action) use ($validate, &$injected) {
            if (! $injected && $action instanceof ActionGroup) {
                $injected = true;

                return $action->actions([$validate, ...$action->getActions()]);
            }

            return $action;
        }, $table->getActions());

        if (! $injected) {
            $rowActions[] = $validate;
        }

        $bulkValidate = BulkAction::make('validate_accounts')
            ->label(self::LABEL)
            ->icon(self::ICON)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Valider les comptes sélectionnés ?')
            ->modalDescription('Seuls les comptes en attente dont l\'e-mail est confirmé sont validés ; les autres sont ignorés. '.self::CONFIRMATION)
            ->modalSubmitActionLabel('Valider')
            ->action(function (Collection $records): void {
                $validated = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if (ValidateCustomerAccount::blockReason($record) !== null) {
                        $skipped++;

                        continue;
                    }

                    app(ValidateCustomerAccount::class)($record);
                    $validated++;
                }

                Notification::make()
                    ->success()
                    ->title('Validation terminée')
                    ->body("{$validated} compte(s) validé(s), {$skipped} ignoré(s) (pas en attente ou e-mail non confirmé).")
                    ->send();
            })
            ->deselectRecordsAfterCompletion();

        $groupInjected = false;
        $bulkActions = array_map(function ($action) use ($bulkValidate, &$groupInjected) {
            if (! $groupInjected && $action instanceof BulkActionGroup) {
                $groupInjected = true;

                return $action->actions([$bulkValidate, ...$action->getActions()]);
            }

            return $action;
        }, $table->getBulkActions());

        if (! $groupInjected) {
            $bulkActions[] = $bulkValidate;
        }

        return $table
            ->actions($rowActions)
            ->bulkActions($bulkActions)
            ->pushFilters([
                Filter::make('pending_validation')
                    ->label('En attente de validation')
                    ->query(fn (Builder $query): Builder => $query->where('pko_status', 'pending')),
            ]);
    }

    /**
     * En-tête de la fiche client (vue + édition).
     *
     * @param  array<int, mixed>  $actions
     * @return array<int, mixed>
     */
    public function headerActions(array $actions): array
    {
        $validate = PageAction::make('validate_account')
            ->label(self::LABEL)
            ->icon(self::ICON)
            ->color('success')
            ->visible(fn (Customer $record): bool => $record->getAttribute('pko_status') === 'pending')
            ->disabled(fn (Customer $record): bool => ValidateCustomerAccount::blockReason($record) !== null)
            ->tooltip(fn (Customer $record): ?string => ValidateCustomerAccount::blockReason($record))
            ->requiresConfirmation()
            ->modalHeading('Valider ce compte ?')
            ->modalDescription(self::CONFIRMATION)
            ->modalSubmitActionLabel('Valider')
            ->action(function (Customer $record, $livewire): void {
                self::validate($record);

                // Page d'édition : sans ce rafraîchissement le Select affiche encore
                // « En attente », et un « Enregistrer » ultérieur dévaliderait le compte.
                if (method_exists($livewire, 'refreshFormData')) {
                    $livewire->refreshFormData(['pko_status']);
                }
            });

        return [$validate, ...$actions];
    }

    private static function validate(Customer $record): void
    {
        try {
            app(ValidateCustomerAccount::class)($record);
        } catch (\DomainException $e) {
            Notification::make()->danger()->title('Validation impossible')->body($e->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Compte validé')
            ->body('Le client a été prévenu par e-mail et peut se connecter.')
            ->send();
    }
}
