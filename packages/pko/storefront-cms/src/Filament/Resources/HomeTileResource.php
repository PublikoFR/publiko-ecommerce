<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources;

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
use Pko\LunarMediaCore\Filament\Forms\Components\MediaPicker;
use Pko\StorefrontCms\Filament\Resources\HomeTileResource\Pages;
use Pko\StorefrontCms\Models\HomeTile;

class HomeTileResource extends Resource
{
    protected static ?string $model = HomeTile::class;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Tuiles accueil';

    protected static ?string $modelLabel = 'tuile accueil';

    protected static ?string $pluralModelLabel = 'Tuiles accueil';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('title')->label('Titre')->required()->maxLength(120),
                TextInput::make('subtitle')->label('Sous-titre')->maxLength(200),
            ]),
            MediaPicker::make('image')->label('Image (800x600 recommandé)')->mediagroup('image'),
            Grid::make(2)->schema([
                TextInput::make('cta_label')->label('Libellé CTA')->maxLength(40),
                TextInput::make('cta_url')->label('URL CTA')->maxLength(500),
            ]),
            Grid::make(2)->schema([
                TextInput::make('position')->label('Ordre')->numeric()->default(0),
                Toggle::make('is_active')->label('Actif')->default(true),
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
                    ->getStateUsing(fn (HomeTile $record) => $record->firstMediaUrl('image')),
                TextColumn::make('title')->label('Titre')->searchable(),
                TextColumn::make('cta_url')->label('URL')->limit(30),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageHomeTiles::route('/')];
    }
}
