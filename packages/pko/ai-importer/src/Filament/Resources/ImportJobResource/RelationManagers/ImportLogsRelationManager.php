<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Pko\AiImporter\Enums\LogLevel;

class ImportLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Logs';

    protected static ?string $icon = 'heroicon-o-queue-list';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->defaultSort('id', 'desc')
            ->poll('5s')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime('H:i:s')->label('Heure'),
                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->color(fn (LogLevel $state): string => match ($state) {
                        LogLevel::Success => 'success',
                        LogLevel::Warning => 'warning',
                        LogLevel::Error => 'danger',
                        LogLevel::Debug => 'gray',
                        LogLevel::Info => 'info',
                    }),
                Tables\Columns\TextColumn::make('row_number')->label('Ligne')->placeholder('—'),
                Tables\Columns\TextColumn::make('message')->wrap()->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->options(collect(LogLevel::cases())->mapWithKeys(fn (LogLevel $l): array => [$l->value => ucfirst($l->value)])->toArray()),
            ]);
    }
}
