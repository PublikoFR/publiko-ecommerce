<?php

declare(strict_types=1);

namespace Pko\Loyalty\Filament\Resources;

use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\Loyalty\Filament\Resources\PointsHistoryResource\Pages;
use Pko\Loyalty\Models\PointsHistory;

class PointsHistoryResource extends BaseResource
{
    protected static ?string $model = PointsHistory::class;

    protected static ?int $navigationSort = 52;

    public static function getLabel(): string
    {
        return 'Historique des points';
    }

    public static function getPluralLabel(): string
    {
        return 'Historique des points';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clock';
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn (PointsHistory $r) => trim(($r->customer?->first_name ?? '').' '.($r->customer?->last_name ?? '')))
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('order_reference')
                    ->label('Commande')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order_total_ht')
                    ->label('Total HT')
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', ' ').' €'),
                Tables\Columns\TextColumn::make('points_earned')
                    ->label('Points')
                    ->numeric(),
            ]);
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => Pages\ListPointsHistory::route('/'),
        ];
    }
}
