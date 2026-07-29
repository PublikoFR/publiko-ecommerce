<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
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
     * Groupes clients : le CheckboxList de Lunar occupe toute la colonne de droite
     * dès qu'il y a une vingtaine de groupes. On le remplace par un multi-select
     * avec recherche (même ergonomie que les tags produit).
     */
    protected static function getCustomerGroupsFormComponent(): Component
    {
        return Select::make('customerGroups')
            ->label(__('lunarpanel::customer.form.customer_groups.label'))
            ->multiple()
            ->searchable()
            ->preload()
            ->placeholder('Rechercher un groupe…')
            ->relationship(
                name: 'customerGroups',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query) => $query->distinct(
                    ['id', 'name', 'handle', 'default']
                )
            );
    }

    /**
     * Liste clients (compacte, pour tenir sur l'écran) :
     * - colonne « Client » = nom + prénom, avec l'e-mail dessous (lien mailto),
     *   recherchable sur prénom / nom / e-mail ;
     * - département (2 premiers chiffres du code postal) + filtre ;
     * - groupes limités aux 3 premiers (puis « … de plus ») ;
     * - actions de ligne regroupées dans un dropdown (dernière colonne) ;
     * - colonnes identifiant fiscal (tax_identifier) et référence compte
     *   (account_ref) retirées.
     */
    public static function getDefaultTable(Table $table): Table
    {
        $table = parent::getDefaultTable($table);

        $columns = collect($table->getColumns())
            ->reject(fn ($column) => in_array($column->getName(), ['tax_identifier', 'account_ref'], true))
            ->keyBy(fn ($column) => $column->getName());

        // Colonne unique nom + prénom, e-mail cliquable (mailto) en dessous.
        // Recherche globale étendue à prénom / nom / e-mail (relation users).
        $client = TextColumn::make('first_name')
            ->label('Client')
            ->html()
            ->formatStateUsing(function (Customer $record): HtmlString {
                $name = trim(($record->first_name ?? '').' '.($record->last_name ?? ''));

                // Empilement vertical (nom en gras, e-mail dessous). Le wrapper
                // cliquable de Filament est en `flex` (ligne) → on lui donne un
                // enfant unique qui stacke lui-même en `flex-col`, aligné à gauche.
                $html = '<span class="flex flex-col items-start leading-tight">'
                    .'<span class="font-medium">'.e($name !== '' ? $name : '—').'</span>';

                if (filled($email = $record->users()->first()?->email)) {
                    $html .= '<span class="text-xs text-gray-500">'.e($email).'</span>';
                }

                return new HtmlString($html.'</span>');
            })
            // Pas de url() ici : la cellule retombe sur le lien de ligne (recordUrl →
            // fiche client, posé par CustomerProfileExtension). L'e-mail reste du
            // texte ; l'envoi de mail se fait via l'action « Envoyer un e-mail » du
            // dropdown (évite un <a mailto> imbriqué dans le <a> de ligne).
            ->searchable(query: fn (Builder $query, string $search): Builder => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhereHas('users', fn (Builder $q) => $q->where('email', 'like', "%{$search}%")))
            ->sortable(['first_name', 'last_name']);

        $departement = TextColumn::make('pko_postcode')
            ->label('Département')
            ->formatStateUsing(fn (?string $state): string => filled($state) ? substr($state, 0, 2) : '—')
            ->sortable();

        // Groupes : 3 premiers affichés (badges), puis « … de plus » ; tooltip = liste complète.
        $groups = TextColumn::make('customerGroups.name')
            ->label('Groupes')
            ->badge()
            ->limitList(3)
            ->tooltip(function (TextColumn $column, Customer $record): ?string {
                if ($record->customerGroups->count() <= $column->getListLimit()) {
                    return null;
                }

                return $record->customerGroups->map(fn ($group) => $group->name)->implode(', ');
            });

        $registeredAt = TextColumn::make('created_at')
            ->label('Date inscription')
            ->dateTime('d/m/Y')
            ->placeholder('—')
            ->sortable();

        // Ordre : client (nom + e-mail), société, département, groupes, inscription.
        $ordered = array_values(array_filter([
            $client,
            $columns->get('company_name'),
            $departement,
            $groups,
            $registeredAt,
        ]));

        $sendEmail = Action::make('sendEmail')
            ->label('Envoyer un e-mail')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->visible(fn (Customer $record): bool => filled($record->users()->first()?->email))
            ->url(fn (Customer $record): ?string => filled($email = $record->users()->first()?->email) ? 'mailto:'.$email : null);

        $impersonate = Action::make('impersonate')
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
            });

        return $table
            ->columns($ordered)
            // Les derniers inscrits en premier.
            ->defaultSort('created_at', 'desc')
            // Toutes les actions de ligne dans un dropdown (dernière colonne) → gain de place.
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    $sendEmail,
                    $impersonate,
                ]),
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
                // Inversion de l'ordre d'inscription. Le tri DOIT passer par
                // baseQuery() : le callback query() d'un filtre est exécuté dans un
                // where() imbriqué (cf. HasFilters::applyFiltersToTableQuery), donc un
                // reorder() posé là n'atteint jamais la requête principale.
                // query() est neutralisé pour empêcher le « where inscription_order = … »
                // par défaut de SelectFilter.
                SelectFilter::make('inscription_order')
                    ->label('Ordre d\'inscription')
                    ->options([
                        'desc' => 'Plus récents d\'abord',
                        'asc' => 'Plus anciens d\'abord',
                    ])
                    ->query(fn (Builder $query): Builder => $query)
                    ->baseQuery(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->reorder('lunar_customers.created_at', $data['value'] === 'asc' ? 'asc' : 'desc')
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
