<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Pko\StorefrontCms\Filament\Forms\Components\MediaPicker;
use Pko\StorefrontCms\Filament\Resources\PostResource\Pages;
use Pko\StorefrontCms\Models\Post;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Actualités';

    protected static ?string $modelLabel = 'actualité';

    protected static ?string $pluralModelLabel = 'Actualités';

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?int $navigationSort = 20;

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
            MediaPicker::make('cover')->label('Image de couverture')->mediagroup('cover')->folder('blog'),
            Textarea::make('excerpt')->label('Extrait')->rows(2)->maxLength(500),
            RichEditor::make('body')->label('Contenu')->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3', 'blockquote'])->columnSpanFull(),
            Grid::make(2)->schema([
                Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié'])->default('draft')->required(),
                DateTimePicker::make('published_at')->label('Date de publication')->default(now()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('cover')->label('')->height(36)
                    ->getStateUsing(fn (Post $record) => $record->firstMediaUrl('cover')),
                TextColumn::make('title')->label('Titre')->searchable()->limit(50),
                TextColumn::make('status')->label('Statut')->badge()->color(fn ($state) => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('published_at')->label('Publié le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié']),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
