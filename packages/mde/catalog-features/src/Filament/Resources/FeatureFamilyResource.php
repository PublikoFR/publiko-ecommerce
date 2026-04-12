<?php

declare(strict_types=1);

namespace Mde\CatalogFeatures\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Support\Resources\BaseResource;
use Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource\Pages;
use Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource\RelationManagers\CollectionsRelationManager;
use Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource\RelationManagers\ValuesRelationManager;
use Mde\CatalogFeatures\Models\FeatureFamily;

class FeatureFamilyResource extends BaseResource
{
    protected static ?string $model = FeatureFamily::class;

    protected static ?int $navigationSort = 20;

    public static function getLabel(): string
    {
        return 'Caractéristique';
    }

    public static function getPluralLabel(): string
    {
        return 'Caractéristiques';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalogue';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-tag';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('handle')
                ->label('Handle')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true)
                ->helperText('Identifiant technique (slug) — utilisé par les imports et le front.'),
            Forms\Components\Toggle::make('multi_value')
                ->label('Valeurs multiples')
                ->helperText('Un produit peut porter plusieurs valeurs de cette famille.')
                ->default(true),
            Forms\Components\Toggle::make('searchable')
                ->label('Indexée pour recherche')
                ->helperText('Utilisé plus tard par le moteur de recherche front.'),
        ]);
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('handle')
                    ->label('Handle')
                    ->fontFamily('mono')
                    ->searchable(),
                Tables\Columns\IconColumn::make('multi_value')
                    ->label('Multi-valeurs')
                    ->boolean(),
                Tables\Columns\IconColumn::make('searchable')
                    ->label('Recherche')
                    ->boolean(),
                Tables\Columns\TextColumn::make('values_count')
                    ->label('Valeurs')
                    ->counts('values'),
                Tables\Columns\TextColumn::make('collections_count')
                    ->label('Catégories liées')
                    ->counts('collections'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ValuesRelationManager::class,
            CollectionsRelationManager::class,
        ];
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => Pages\ListFeatureFamilies::route('/'),
            'create' => Pages\CreateFeatureFamily::route('/create'),
            'edit' => Pages\EditFeatureFamily::route('/{record}/edit'),
            'view' => Pages\ViewFeatureFamily::route('/{record}'),
        ];
    }
}
