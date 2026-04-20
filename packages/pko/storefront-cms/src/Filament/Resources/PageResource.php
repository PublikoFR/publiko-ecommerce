<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Pko\StorefrontCms\Filament\Resources\PageResource\Pages;
use Pko\StorefrontCms\Models\Page;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Pages CMS';

    protected static ?string $modelLabel = 'page CMS';

    protected static ?string $pluralModelLabel = 'Pages CMS';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(200)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ? null : $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(200),
            ]),
            Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié'])->default('published')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')->label('Titre')->searchable(),
                TextColumn::make('slug')->label('URL')->formatStateUsing(fn ($state) => "/pages/{$state}")->copyable(),
                TextColumn::make('status')->label('Statut')->badge()->color(fn ($state) => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('updated_at')->label('Modifié')->dateTime('d/m/Y')->since(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
