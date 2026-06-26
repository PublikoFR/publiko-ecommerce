<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\ShippingCommon\Filament\Clusters\Shipping;
use Pko\ShippingCommon\Filament\Resources\ShippingSurchargeResource\Pages;
use Pko\ShippingCommon\Models\ShippingSurcharge;

class ShippingSurchargeResource extends BaseResource
{
    protected static ?string $model = ShippingSurcharge::class;

    protected static ?string $cluster = Shipping::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('pko-shipping-common::admin.surcharge.nav');
    }

    public static function getLabel(): string
    {
        return __('pko-shipping-common::admin.surcharge.label');
    }

    public static function getPluralLabel(): string
    {
        return __('pko-shipping-common::admin.surcharge.plural_label');
    }

    public static function getDefaultForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label(__('pko-shipping-common::admin.surcharge.code'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(64)
                ->helperText('Identifiant technique (snake_case, unique).'),

            Forms\Components\TextInput::make('label')
                ->label(__('pko-shipping-common::admin.surcharge.label_field'))
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('mode')
                ->label(__('pko-shipping-common::admin.surcharge.mode'))
                ->options([
                    'auto' => __('pko-shipping-common::admin.surcharge.mode_auto'),
                    'quote' => __('pko-shipping-common::admin.surcharge.mode_quote'),
                    'rebill' => __('pko-shipping-common::admin.surcharge.mode_rebill'),
                ])
                ->required()
                ->default('quote')
                ->helperText(fn (Forms\Get $get): string => match ($get('mode')) {
                    'auto' => __('pko-shipping-common::admin.surcharge.mode_auto_help'),
                    'rebill' => __('pko-shipping-common::admin.surcharge.mode_rebill_help'),
                    default => __('pko-shipping-common::admin.surcharge.mode_quote_help'),
                })
                ->live(),

            Forms\Components\TextInput::make('amount_cents')
                ->label(__('pko-shipping-common::admin.surcharge.amount_cents'))
                ->numeric()
                ->minValue(0)
                ->nullable()
                ->helperText('Montant en centimes (ex: 1500 = 15,00 €). Laisser vide si montant variable.'),

            Forms\Components\Textarea::make('rule')
                ->label(__('pko-shipping-common::admin.surcharge.rule'))
                ->rows(3)
                ->nullable()
                ->helperText('Critères d\'application en JSON (optionnel).'),

            Forms\Components\Toggle::make('enabled')
                ->label(__('pko-shipping-common::admin.surcharge.enabled'))
                ->default(true),
        ]);
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('pko-shipping-common::admin.surcharge.code'))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('label')
                    ->label(__('pko-shipping-common::admin.surcharge.label_field'))
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('mode')
                    ->label(__('pko-shipping-common::admin.surcharge.mode'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'auto' => 'Auto',
                        'rebill' => 'Refacturé',
                        default => 'Sur devis',
                    })
                    ->colors([
                        'success' => 'auto',
                        'warning' => 'quote',
                        'info' => 'rebill',
                    ]),

                Tables\Columns\TextColumn::make('amount_cents')
                    ->label(__('pko-shipping-common::admin.surcharge.amount_cents'))
                    ->formatStateUsing(fn (?int $state): string => $state !== null
                        ? number_format($state / 100, 2, ',', ' ').' €'
                        : '—'),

                Tables\Columns\IconColumn::make('enabled')
                    ->label(__('pko-shipping-common::admin.surcharge.enabled'))
                    ->boolean(),
            ])
            ->defaultSort('code')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingSurcharges::route('/'),
            'create' => Pages\CreateShippingSurcharge::route('/create'),
            'edit' => Pages\EditShippingSurcharge::route('/{record}/edit'),
        ];
    }
}
