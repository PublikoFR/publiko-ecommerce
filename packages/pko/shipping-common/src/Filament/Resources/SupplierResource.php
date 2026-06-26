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
use Pko\ShippingCommon\Filament\Resources\SupplierResource\Pages;
use Pko\ShippingCommon\Models\Supplier;

class SupplierResource extends BaseResource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $cluster = Shipping::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('pko-shipping-common::admin.supplier.nav');
    }

    public static function getLabel(): string
    {
        return __('pko-shipping-common::admin.supplier.label');
    }

    public static function getPluralLabel(): string
    {
        return __('pko-shipping-common::admin.supplier.plural_label');
    }

    public static function getDefaultForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('pko-shipping-common::admin.supplier.name'))
                ->required()
                ->maxLength(255),

            Forms\Components\Toggle::make('bl_neutre')
                ->label(__('pko-shipping-common::admin.supplier.bl_neutre'))
                ->helperText(__('pko-shipping-common::admin.supplier.bl_neutre_help'))
                ->default(false),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('lead_time_min_days')
                    ->label(__('pko-shipping-common::admin.supplier.lead_time_min'))
                    ->numeric()
                    ->minValue(0)
                    ->nullable(),

                Forms\Components\TextInput::make('lead_time_max_days')
                    ->label(__('pko-shipping-common::admin.supplier.lead_time_max'))
                    ->numeric()
                    ->minValue(0)
                    ->nullable(),
            ]),

            Forms\Components\Textarea::make('notes')
                ->label(__('pko-shipping-common::admin.supplier.notes'))
                ->rows(3)
                ->nullable(),
        ]);
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('pko-shipping-common::admin.supplier.name'))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('bl_neutre')
                    ->label(__('pko-shipping-common::admin.supplier.bl_neutre'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('lead_time')
                    ->label('Délai')
                    ->getStateUsing(fn (Supplier $record): string => $record->lead_time_min_days !== null
                        ? $record->lead_time_min_days.' – '.($record->lead_time_max_days ?? '?').' j.'
                        : '—'),
            ])
            ->defaultSort('name')
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
