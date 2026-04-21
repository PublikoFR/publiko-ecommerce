<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Pko\StorefrontCms\Filament\Resources\PostTypeResource\Pages;
use Pko\StorefrontCms\Models\PostType;

class PostTypeResource extends Resource
{
    protected static ?string $model = PostType::class;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Types de contenus';

    protected static ?string $modelLabel = 'type de contenu';

    protected static ?string $pluralModelLabel = 'Types de contenus';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 22;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('label')
                    ->label('Libellé')
                    ->required()
                    ->maxLength(128)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if (! $get('handle')) {
                            $set('handle', Str::slug((string) $state));
                        }
                        if (! $get('url_segment')) {
                            $set('url_segment', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('handle')
                    ->label('Handle technique')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->helperText('Identifiant immuable (ex: article, page, guide).'),
            ]),
            Grid::make(2)->schema([
                TextInput::make('url_segment')
                    ->label('Segment URL (1er segment de l\'URL publique)')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                if (in_array(strtolower((string) $value), PostType::reservedUrlSegments(), true)) {
                                    $fail("Le segment « {$value} » est réservé et ne peut pas être utilisé.");
                                }
                            };
                        },
                    ])
                    ->helperText('Ex: "guide" → /guide/{slug}. Évitez les collisions avec les routes projet.'),
                TextInput::make('icon')
                    ->label('Icône (heroicon)')
                    ->maxLength(64)
                    ->placeholder('heroicon-o-book-open')
                    ->default('heroicon-o-document-text'),
            ]),
            TextInput::make('layout')
                ->label('Layout Blade (optionnel)')
                ->maxLength(255)
                ->placeholder('storefront-cms::posts.show')
                ->helperText('Nom de la vue Blade à utiliser. Vide = layout par défaut (posts.show).'),
            TextInput::make('sort_order')
                ->label('Ordre')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('label')->label('Libellé')->searchable()->sortable(),
                TextColumn::make('handle')->label('Handle')->fontFamily('mono'),
                TextColumn::make('url_segment')->label('URL')->formatStateUsing(fn ($state) => "/{$state}/{slug}")->fontFamily('mono'),
                TextColumn::make('posts_count')->label('Contenus')->counts('posts'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPostTypes::route('/'),
            'create' => Pages\CreatePostType::route('/create'),
            'edit' => Pages\EditPostType::route('/{record}/edit'),
        ];
    }
}
