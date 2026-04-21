<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Pko\StorefrontCms\Filament\Resources\NewsletterSubscriberResource\Pages;
use Pko\StorefrontCms\Models\NewsletterSubscriber;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $navigationGroup = 'Storefront';

    public static function getNavigationLabel(): string
    {
        return __('pko-storefront-cms::admin.newsletter.nav');
    }

    public static function getModelLabel(): string
    {
        return __('pko-storefront-cms::admin.newsletter.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pko-storefront-cms::admin.newsletter.plural');
    }

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('consent_at', 'desc')
            ->columns([
                TextColumn::make('email')->label('E-mail')->searchable()->copyable(),
                TextColumn::make('consent_at')->label('Inscrit le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('unsubscribed_at')->label('Désinscrit')->dateTime('d/m/Y')->placeholder('—'),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNewsletterSubscribers::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
