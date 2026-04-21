<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
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
use Pko\LunarMediaCore\Filament\Forms\Components\MediaPicker;
use Pko\StorefrontCms\Filament\Resources\PostResource\Pages;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Models\PostType;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationGroup = 'Storefront';

    public static function getNavigationLabel(): string
    {
        return __('pko-storefront-cms::admin.post.nav');
    }

    public static function getModelLabel(): string
    {
        return __('pko-storefront-cms::admin.post.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pko-storefront-cms::admin.post.plural');
    }

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(3)->schema([
                Select::make('post_type_id')
                    ->label('Type de contenu')
                    ->relationship('postType', 'label')
                    ->required()
                    ->default(fn () => PostType::where('handle', 'article')->value('id'))
                    ->helperText(fn ($state) => $state
                        ? '/'.(PostType::find($state)?->url_segment ?? '?').'/{slug}'
                        : null
                    )
                    ->live(),
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(200)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ? null : $set('slug', Str::slug((string) $state)))
                    ->columnSpan(2),
            ]),
            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(200)
                ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule, $get) {
                    return $rule->where('post_type_id', $get('post_type_id'));
                }),
            MediaPicker::make('cover')->label('Image de couverture')->mediagroup('cover')->folder('blog'),
            Textarea::make('excerpt')->label('Extrait')->rows(2)->maxLength(500),
            Grid::make(3)->schema([
                Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié'])->default('draft')->required(),
                DateTimePicker::make('published_at')->label('Date de publication')->default(now()),
                TextInput::make('seo_title')->label('SEO Titre')->maxLength(255),
            ]),
            Textarea::make('seo_description')->label('SEO Description')->rows(2)->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('cover')->label('')->height(36)
                    ->getStateUsing(fn (Post $record) => $record->firstMediaUrl('cover')),
                TextColumn::make('postType.label')->label('Type')->badge(),
                TextColumn::make('title')->label('Titre')->searchable()->limit(50),
                TextColumn::make('status')->label('Statut')->badge()->color(fn ($state) => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('published_at')->label('Publié le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('post_type_id')
                    ->label('Type')
                    ->relationship('postType', 'label'),
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
