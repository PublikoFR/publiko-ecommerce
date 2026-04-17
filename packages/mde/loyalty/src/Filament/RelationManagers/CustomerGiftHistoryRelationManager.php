<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Mde\Loyalty\Enums\GiftStatus;
use Mde\Loyalty\Models\GiftHistory;

class CustomerGiftHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'giftHistory';

    protected static ?string $title = 'Cadeaux fidélité';

    protected static ?string $icon = 'heroicon-o-gift';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('status')
                ->label('Statut')
                ->options(GiftStatus::options())
                ->required(),
            Textarea::make('admin_notes')
                ->label('Notes admin')
                ->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('unlocked_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tier.name')
                    ->label('Palier'),
                Tables\Columns\TextColumn::make('tier.gift_title')
                    ->label('Cadeau')
                    ->limit(40),
                Tables\Columns\TextColumn::make('points_at_unlock')
                    ->label('Points')
                    ->numeric(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (GiftStatus $state) => $state->label())
                    ->color(fn (GiftStatus $state) => match ($state) {
                        GiftStatus::Pending => 'gray',
                        GiftStatus::Processing => 'warning',
                        GiftStatus::Sent => 'success',
                    }),
                Tables\Columns\TextColumn::make('unlocked_at')
                    ->label('Débloqué')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn (GiftHistory $record) => $record->update(['admin_viewed' => true, 'status_updated_at' => now()])),
            ]);
    }
}
