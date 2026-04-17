<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Resources;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Mde\StorefrontCms\Filament\Resources\HomeSlideResource\Pages;
use Mde\StorefrontCms\Models\HomeSlide;

class HomeSlideResource extends Resource
{
    protected static ?string $model = HomeSlide::class;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Slides accueil';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('title')->label('Titre')->required()->maxLength(200),
                TextInput::make('subtitle')->label('Sous-titre')->maxLength(255),
            ]),
            TextInput::make('image_url')->label('URL image (1920x500 recommandé)')->url()->maxLength(500),
            Grid::make(2)->schema([
                TextInput::make('cta_label')->label('Libellé CTA')->maxLength(60),
                TextInput::make('cta_url')->label('URL CTA')->maxLength(500),
            ]),
            Grid::make(3)->schema([
                ColorPicker::make('bg_color')->label('Couleur fond')->default('#1e3a8a'),
                ColorPicker::make('text_color')->label('Couleur texte')->default('#ffffff'),
                TextInput::make('position')->label('Ordre')->numeric()->default(0),
            ]),
            Grid::make(3)->schema([
                Toggle::make('is_active')->label('Actif')->default(true),
                DateTimePicker::make('starts_at')->label('Début (optionnel)'),
                DateTimePicker::make('ends_at')->label('Fin (optionnel)'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                ImageColumn::make('image_url')->label('Visuel')->circular(false)->height(40),
                TextColumn::make('title')->label('Titre')->searchable()->limit(40),
                TextColumn::make('cta_label')->label('CTA')->limit(20),
                IconColumn::make('is_active')->label('Actif')->boolean(),
                TextColumn::make('ends_at')->label('Fin')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageHomeSlides::route('/'),
        ];
    }

    protected static function flushCache(): void
    {
        Cache::forget('mde.home.slides.v1');
    }
}
