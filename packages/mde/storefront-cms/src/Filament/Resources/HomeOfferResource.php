<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Mde\StorefrontCms\Filament\Forms\Components\MediaPicker;
use Mde\StorefrontCms\Filament\Resources\HomeOfferResource\Pages;
use Mde\StorefrontCms\Models\HomeOffer;

class HomeOfferResource extends Resource
{
    protected static ?string $model = HomeOffer::class;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Offres du moment';

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('title')->label('Titre')->required()->maxLength(120),
                TextInput::make('subtitle')->label('Sous-titre')->maxLength(200),
            ]),
            MediaPicker::make('image')->label('Image')->mediagroup('image'),
            Grid::make(3)->schema([
                TextInput::make('badge')->label('Badge (ex: -25%)')->maxLength(40),
                TextInput::make('cta_label')->label('Libellé CTA')->maxLength(40),
                TextInput::make('cta_url')->label('URL CTA')->maxLength(500),
            ]),
            Grid::make(3)->schema([
                TextInput::make('position')->label('Ordre')->numeric()->default(0),
                Toggle::make('is_active')->label('Actif')->default(true),
                DateTimePicker::make('ends_at')->label('Fin offre'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('position')->label('#'),
                ImageColumn::make('image')->label('Image')->height(40)
                    ->getStateUsing(fn (HomeOffer $record) => $record->firstMediaUrl('image')),
                TextColumn::make('title')->label('Titre')->searchable(),
                TextColumn::make('badge')->label('Badge')->badge(),
                TextColumn::make('ends_at')->label('Fin')->dateTime('d/m/Y'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageHomeOffers::route('/')];
    }
}
