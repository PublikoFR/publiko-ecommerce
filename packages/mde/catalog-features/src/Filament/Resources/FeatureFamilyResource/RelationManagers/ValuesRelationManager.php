<?php

declare(strict_types=1);

namespace Mde\CatalogFeatures\Filament\Resources\FeatureFamilyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'Valeurs';

    protected static ?string $modelLabel = 'valeur';

    protected static ?string $pluralModelLabel = 'valeurs';

    public function form(Form $form): Form
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
                ->helperText('Slug stable pour imports / front.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('handle')
                    ->label('Handle')
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Produits')
                    ->counts('products'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
