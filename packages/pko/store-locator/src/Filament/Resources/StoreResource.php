<?php

declare(strict_types=1);

namespace Pko\StoreLocator\Filament\Resources;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Pko\StoreLocator\Filament\Resources\StoreResource\Pages;
use Pko\StoreLocator\Models\Store;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Magasins';

    protected static ?string $modelLabel = 'magasin';

    protected static ?string $pluralModelLabel = 'Magasins';

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 25;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identité')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('Nom du magasin')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ? null : $set('slug', Str::slug((string) $state))),
                    TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(120),
                ]),
                Toggle::make('is_active')->label('Actif')->default(true),
            ]),
            Section::make('Adresse')->schema([
                TextInput::make('address_line_1')->label('Adresse')->required()->maxLength(200),
                TextInput::make('address_line_2')->label('Complément')->maxLength(200),
                Grid::make(3)->schema([
                    TextInput::make('postcode')->label('Code postal')->required()->maxLength(16),
                    TextInput::make('city')->label('Ville')->required()->maxLength(120),
                    TextInput::make('country_iso2')->label('Pays')->default('FR')->maxLength(2),
                ]),
                Grid::make(2)->schema([
                    TextInput::make('lat')->label('Latitude')->numeric()->step(0.0000001),
                    TextInput::make('lng')->label('Longitude')->numeric()->step(0.0000001),
                ]),
            ])->collapsible(),
            Section::make('Contact')->schema([
                Grid::make(2)->schema([
                    TextInput::make('phone')->label('Téléphone')->tel()->maxLength(30),
                    TextInput::make('email')->label('E-mail')->email()->maxLength(150),
                ]),
            ])->collapsible(),
            Section::make('Horaires d\'ouverture')->schema([
                KeyValue::make('hours')
                    ->label('Jour → Horaires (ex: "8h-12h / 14h-18h" ou "Fermé")')
                    ->keyLabel('Jour')
                    ->valueLabel('Plage')
                    ->addActionLabel('Ajouter un jour'),
            ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Magasin')->searchable()->sortable(),
                TextColumn::make('city')->label('Ville')->searchable(),
                TextColumn::make('postcode')->label('CP'),
                TextColumn::make('phone')->label('Tél')->toggleable(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
