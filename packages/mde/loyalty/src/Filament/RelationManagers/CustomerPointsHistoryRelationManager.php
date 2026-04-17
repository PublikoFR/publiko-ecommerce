<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerPointsHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'pointsHistory';

    protected static ?string $title = 'Historique des points';

    protected static ?string $icon = 'heroicon-o-clock';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('order_reference')
                    ->label('Commande'),
                Tables\Columns\TextColumn::make('order_total_ht')
                    ->label('Total HT')
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', ' ').' €'),
                Tables\Columns\TextColumn::make('points_earned')
                    ->label('Points gagnés')
                    ->numeric(),
            ]);
    }
}
