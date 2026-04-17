<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Mde\StorefrontCms\Filament\Resources\NewsletterSubscriberResource\Pages;
use Mde\StorefrontCms\Models\NewsletterSubscriber;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Abonnés newsletter';

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
