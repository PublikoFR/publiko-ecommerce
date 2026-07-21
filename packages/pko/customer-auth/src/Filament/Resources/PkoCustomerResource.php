<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources;

use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lunar\Admin\Filament\Resources\CustomerResource;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Actions\ImpersonateCustomerUser;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoCreateCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoEditCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoListCustomers;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoViewCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\RelationManagers\NegotiatedPricesRelationManager;

/**
 * Swap de la CustomerResource de Lunar (via AppServiceProvider::swapLunarResources).
 * Objectif : PkoViewCustomer fusionne le contenu (infolist) et les relations
 * (Commandes / Adresses / Utilisateur) en un seul groupe d'onglets, avec les infos
 * client comme premier onglet — navigation plus pratique sur la fiche.
 *
 * Slug 'customers' conservé → les routes/URLs (et CustomerResource::getUrl() appelé
 * ailleurs) restent résolus. Les pages Pko redéclarent $resource pour éviter le
 * RouteNotFoundException (les pages Lunar hardcodent $resource = CustomerResource).
 */
class PkoCustomerResource extends CustomerResource
{
    protected static ?string $slug = 'customers';

    /**
     * Liste clients : on ajoute e-mail + département (2 premiers chiffres du code
     * postal) + filtre département, et on retire les colonnes identifiant fiscal
     * (tax_identifier) et référence du compte (account_ref).
     */
    public static function getDefaultTable(Table $table): Table
    {
        $table = parent::getDefaultTable($table);

        $columns = collect($table->getColumns())
            ->reject(fn ($column) => in_array($column->getName(), ['tax_identifier', 'account_ref'], true))
            ->keyBy(fn ($column) => $column->getName());

        $email = TextColumn::make('users.email')
            ->label('E-mail')
            ->searchable()
            ->sortable()
            ->copyable();

        $departement = TextColumn::make('pko_postcode')
            ->label('Département')
            ->formatStateUsing(fn (?string $state): string => filled($state) ? substr($state, 0, 2) : '—')
            ->sortable();

        // Ordre : prénom, nom, société, e-mail, département, groupes.
        $ordered = array_values(array_filter([
            $columns->get('first_name'),
            $columns->get('last_name'),
            $columns->get('company_name'),
            $email,
            $departement,
            $columns->get('customerGroups.name'),
        ]));

        return $table
            ->columns($ordered)
            ->pushActions([
                Action::make('impersonate')
                    ->label('Se connecter en tant que')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Se connecter en tant que ce client ?')
                    ->modalDescription('Vous serez connecté sur le site (front) avec le compte de ce client. L\'accès à l\'espace pro est forcé, même si le compte est en attente de validation ou son e-mail non confirmé : ce que vous verrez peut donc différer de ce que voit réellement le client. Pour revenir, il suffit de vous déconnecter normalement.')
                    ->visible(fn (Customer $record): bool => $record->users()->exists())
                    ->action(function (Customer $record) {
                        $user = $record->users()->first();

                        if (! $user) {
                            Notification::make()
                                ->warning()
                                ->title('Impossible : ce client n\'a aucun utilisateur rattaché.')
                                ->send();

                            return null;
                        }

                        // Connexion sur le guard front (web / provider users) —
                        // distinct du guard staff admin, qui reste inchangé.
                        // Cf. ImpersonateCustomerUser : la bascule temporaire du
                        // guard par défaut est indispensable, sans quoi les
                        // listeners Lunar du Login tapent sur le Staff.
                        app(ImpersonateCustomerUser::class)($user);

                        return redirect('/');
                    }),
            ])
            ->filters([
                ...$table->getFilters(),
                SelectFilter::make('departement')
                    ->label('Département')
                    ->options(fn (): array => Customer::query()
                        ->whereNotNull('pko_postcode')
                        ->where('pko_postcode', '!=', '')
                        ->pluck('pko_postcode')
                        ->map(fn (string $postcode): string => substr($postcode, 0, 2))
                        ->unique()
                        ->sort()
                        ->values()
                        ->mapWithKeys(fn (string $dep): array => [$dep => $dep])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->where('pko_postcode', 'like', $data['value'].'%')
                        : $query),
            ]);
    }

    public static function getDefaultRelations(): array
    {
        return array_merge(parent::getDefaultRelations(), [
            NegotiatedPricesRelationManager::class,
        ]);
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListCustomers::route('/'),
            'create' => PkoCreateCustomer::route('/create'),
            'edit' => PkoEditCustomer::route('/{record}/edit'),
            'view' => PkoViewCustomer::route('/{record}'),
        ];
    }
}
