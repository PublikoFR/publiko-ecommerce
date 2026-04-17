<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Support\Resources\BaseResource;
use Mde\Loyalty\Filament\Resources\LoyaltyTierResource\Pages;
use Mde\Loyalty\Models\LoyaltyTier;

class LoyaltyTierResource extends BaseResource
{
    protected static ?string $model = LoyaltyTier::class;

    protected static ?int $navigationSort = 50;

    public static function getLabel(): string
    {
        return 'Palier de fidélité';
    }

    public static function getPluralLabel(): string
    {
        return 'Paliers de fidélité';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-trophy';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nom du palier')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('points_required')
                ->label('Points requis')
                ->required()
                ->numeric()
                ->minValue(1),
            Forms\Components\TextInput::make('gift_title')
                ->label('Titre du cadeau')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('gift_description')
                ->label('Description du cadeau')
                ->rows(3),
            Forms\Components\TextInput::make('gift_image_url')
                ->label('URL image cadeau')
                ->url()
                ->maxLength(500),
            Forms\Components\TextInput::make('position')
                ->label('Position')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('active')
                ->label('Actif')
                ->default(true),
        ]);
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('points_required')
            ->columns([
                Tables\Columns\ImageColumn::make('gift_image_url')
                    ->label('Cadeau')
                    ->size(50),
                Tables\Columns\TextColumn::make('name')
                    ->label('Palier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('points_required')
                    ->label('Points')
                    ->sortable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('gift_title')
                    ->label('Titre cadeau')
                    ->limit(40),
                Tables\Columns\IconColumn::make('active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyTiers::route('/'),
            'create' => Pages\CreateLoyaltyTier::route('/create'),
            'edit' => Pages\EditLoyaltyTier::route('/{record}/edit'),
        ];
    }
}
