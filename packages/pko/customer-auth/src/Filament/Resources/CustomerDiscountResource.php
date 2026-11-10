<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources;

use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Filament\Resources\CustomerDiscountResource\Pages\ManageCustomerDiscounts;
use Pko\CustomerAuth\Models\CustomerDiscount;
use Pko\CustomerAuth\Support\CustomerDiscountTargets;

/**
 * Vue globale des remises client % (tous clients confondus) : filtrable par
 * client, type et cible, éditable en modale. La saisie par client se fait aussi
 * depuis l'onglet « Remises » de la fiche client (CustomerDiscountsRelationManager),
 * qui partage le même formulaire ({@see self::targetFields()}).
 */
class CustomerDiscountResource extends Resource
{
    protected static ?string $model = CustomerDiscount::class;

    protected static ?string $slug = 'customer-discounts';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $modelLabel = 'remise client';

    protected static ?string $pluralModelLabel = 'remises clients';

    protected static ?string $navigationLabel = 'Remises clients';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('customer_id')
                ->label('Client')
                ->relationship('customer', 'company_name')
                ->getOptionLabelFromRecordUsing(fn (Customer $record): string => CustomerDiscountTargets::customerLabel($record))
                ->searchable(['company_name', 'first_name', 'last_name'])
                ->live()
                ->required()
                ->columnSpanFull(),
            ...static::targetFields(fn (Get $get): mixed => $get('customer_id')),
        ]);
    }

    /**
     * Champs « type de cible / cible / % », partagés avec le RelationManager.
     * Validation : 0 < % ≤ 100 (2 décimales), une seule remise par client + cible.
     *
     * @param  Closure(Get): mixed  $customerId  client courant (form ou fiche)
     * @return array<int, Forms\Components\Component>
     */
    public static function targetFields(Closure $customerId): array
    {
        return [
            Forms\Components\Select::make('target_type')
                ->label('Type de cible')
                ->options(CustomerDiscountTargets::typeOptions())
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('target_id', null))
                ->required(),
            Forms\Components\Select::make('target_id')
                ->label('Cible')
                ->options(fn (Get $get): array => app(CustomerDiscountTargets::class)->options($get('target_type')))
                ->getOptionLabelUsing(fn ($value, Get $get): ?string => app(CustomerDiscountTargets::class)->label($get('target_type'), $value))
                ->searchable()
                ->disabled(fn (Get $get): bool => blank($get('target_type')))
                ->placeholder('Choisissez d\'abord un type de cible')
                ->helperText(fn (Get $get): ?string => $get('target_type') === CustomerDiscount::TARGET_COLLECTION
                    ? 'La remise couvre aussi toutes les sous-catégories.'
                    : null)
                ->required()
                ->unique(
                    table: CustomerDiscount::class,
                    column: 'target_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('customer_id', (int) $customerId($get))
                        ->where('target_type', (string) $get('target_type')),
                )
                ->validationMessages([
                    'unique' => 'Ce client a déjà une remise sur cette cible.',
                ]),
            Forms\Components\TextInput::make('percent')
                ->label('Remise')
                ->numeric()
                ->step(0.01)
                ->maxValue(100)
                ->rules(['gt:0', 'decimal:0,2'])
                ->validationMessages([
                    'gt' => 'La remise doit être strictement supérieure à 0 %.',
                ])
                ->suffix('%')
                ->required(),
        ];
    }

    /**
     * Colonnes « type / cible / % / modifié le », partagées avec le RelationManager.
     *
     * @return array<int, Tables\Columns\Column>
     */
    public static function discountColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('target_type')
                ->label('Type')
                ->badge()
                ->color('gray')
                ->formatStateUsing(fn (?string $state): string => CustomerDiscountTargets::typeLabel($state))
                ->sortable(),
            Tables\Columns\TextColumn::make('target_id')
                ->label('Cible')
                ->formatStateUsing(fn (CustomerDiscount $record): ?string => app(CustomerDiscountTargets::class)->label($record->target_type, $record->target_id))
                ->wrap(),
            Tables\Columns\TextColumn::make('percent')
                ->label('Remise')
                ->formatStateUsing(fn ($state): string => static::formatPercent($state))
                ->alignEnd()
                ->sortable(),
            Tables\Columns\TextColumn::make('updated_at')
                ->label('Modifiée le')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function formatPercent(mixed $percent): string
    {
        $value = rtrim(rtrim(number_format((float) $percent, 2, ',', ''), '0'), ',');

        return "-{$value} %";
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('customer'))
            ->columns([
                Tables\Columns\TextColumn::make('customer_id')
                    ->label('Client')
                    ->formatStateUsing(fn (CustomerDiscount $record): string => CustomerDiscountTargets::customerLabel($record->customer))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'customer',
                        fn (Builder $q) => $q->where('company_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"),
                    )),
                ...static::discountColumns(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Client')
                    ->relationship('customer', 'company_name')
                    ->getOptionLabelFromRecordUsing(fn (Customer $record): string => CustomerDiscountTargets::customerLabel($record))
                    ->searchable(),
                Tables\Filters\Filter::make('target')
                    ->label('Cible')
                    ->form([
                        Forms\Components\Select::make('target_type')
                            ->label('Type de cible')
                            ->options(CustomerDiscountTargets::typeOptions())
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('target_id', null)),
                        Forms\Components\Select::make('target_id')
                            ->label('Cible')
                            ->options(fn (Get $get): array => app(CustomerDiscountTargets::class)->options($get('target_type')))
                            ->searchable()
                            ->disabled(fn (Get $get): bool => blank($get('target_type'))),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['target_type'] ?? null), fn (Builder $q) => $q->where('target_type', $data['target_type']))
                        ->when(filled($data['target_id'] ?? null), fn (Builder $q) => $q->where('target_id', (int) $data['target_id'])))
                    ->indicateUsing(function (array $data): array {
                        if (blank($data['target_type'] ?? null)) {
                            return [];
                        }

                        $label = CustomerDiscountTargets::typeLabel($data['target_type']);
                        if (filled($data['target_id'] ?? null)) {
                            $label .= ' : '.app(CustomerDiscountTargets::class)->label($data['target_type'], $data['target_id']);
                        }

                        return [$label];
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Aucune remise client')
            ->emptyStateDescription('Les remises se saisissent ici ou depuis l\'onglet « Remises » de la fiche client.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomerDiscounts::route('/'),
        ];
    }
}
