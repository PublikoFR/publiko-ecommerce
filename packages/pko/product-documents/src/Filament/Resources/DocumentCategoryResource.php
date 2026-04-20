<?php

declare(strict_types=1);

namespace Pko\ProductDocuments\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\ProductDocuments\Filament\Resources\DocumentCategoryResource\Pages;
use Pko\ProductDocuments\Models\DocumentCategory;

class DocumentCategoryResource extends BaseResource
{
    protected static ?string $model = DocumentCategory::class;

    protected static ?int $navigationSort = 25;

    public static function getLabel(): string
    {
        return 'Catégorie de document';
    }

    public static function getPluralLabel(): string
    {
        return 'Catégories de documents';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalogue';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('label')
                ->label('Libellé')
                ->required()
                ->maxLength(255)
                ->live(debounce: 400)
                ->afterStateUpdated(function (Forms\Set $set, ?string $state, string $operation): void {
                    if ($operation === 'create' && $state !== null) {
                        $set('handle', Str::slug($state));
                    }
                }),
            Forms\Components\TextInput::make('handle')
                ->label('Handle')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true)
                ->helperText('Identifiant technique (slug) — généré automatiquement.'),
            Forms\Components\TextInput::make('sort_order')
                ->label('Ordre')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('handle')
                    ->label('Handle')
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Documents liés')
                    ->counts('documents'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => Pages\ListDocumentCategories::route('/'),
            'create' => Pages\CreateDocumentCategory::route('/create'),
            'edit' => Pages\EditDocumentCategory::route('/{record}/edit'),
        ];
    }
}
