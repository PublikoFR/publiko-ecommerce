<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\LogPhase;
use Pko\AiImporter\Filament\Resources\ImportJobResource\Pages\ViewImportJob;

class ImportLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Journal détaillé';

    protected static ?string $icon = 'heroicon-o-queue-list';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime('H:i:s')->label('Heure'),
                Tables\Columns\TextColumn::make('phase')
                    ->label('Phase')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?LogPhase $state): string => ($state ?? LogPhase::Parse)->label())
                    ->placeholder(LogPhase::Parse->label()),
                Tables\Columns\TextColumn::make('level')
                    ->label('Niveau')
                    ->badge()
                    ->formatStateUsing(fn (LogLevel $state): string => self::levelLabel($state))
                    ->color(fn (LogLevel $state): string => match ($state) {
                        LogLevel::Success => 'success',
                        LogLevel::Warning => 'warning',
                        LogLevel::Error => 'danger',
                        LogLevel::Debug => 'gray',
                        LogLevel::Info => 'info',
                    }),
                Tables\Columns\TextColumn::make('row_number')->label('Ligne')->placeholder('—'),
                Tables\Columns\TextColumn::make('message')->label('Message')->wrap()->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('phase')
                    ->label('Phase')
                    ->options(collect(LogPhase::cases())->mapWithKeys(fn (LogPhase $p): array => [$p->value => $p->label()])->toArray()),
                Tables\Filters\SelectFilter::make('level')
                    ->label('Niveau')
                    ->multiple()
                    ->options(collect(LogLevel::cases())->mapWithKeys(fn (LogLevel $l): array => [$l->value => self::levelLabel($l)])->toArray()),
            ])
            ->emptyStateHeading('Journal vide')
            ->emptyStateDescription('Les événements de la préparation et de l\'import s\'afficheront ici.');
    }

    /** Rechargé au rythme de la page de l'import, pendant qu'un traitement écrit dans le journal. */
    #[On(ViewImportJob::FLOW_UPDATED)]
    public function refreshFromFlow(): void {}

    private static function levelLabel(LogLevel $level): string
    {
        return match ($level) {
            LogLevel::Success => 'Succès',
            LogLevel::Warning => 'Avertissement',
            LogLevel::Error => 'Erreur',
            LogLevel::Info => 'Info',
            LogLevel::Debug => 'Détail technique',
        };
    }
}
