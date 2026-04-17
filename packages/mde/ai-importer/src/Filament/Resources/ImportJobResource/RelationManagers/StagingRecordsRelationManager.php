<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources\ImportJobResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Mde\AiImporter\Enums\StagingStatus;
use Mde\AiImporter\Models\StagingRecord;

/**
 * Preview & edit of parsed rows before the import is triggered.
 *
 * The `data` column is a JSON blob whose schema is defined by the mapping
 * config — we can't display a static columns list. Instead we show a
 * summary (reference + name + price_cents + status) and hand editing off
 * to a JSON textarea in the edit modal. A full visual editor is tracked
 * for phase 5+.
 */
class StagingRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'stagingRecords';

    protected static ?string $title = 'Staging (preview)';

    protected static ?string $icon = 'heroicon-o-table-cells';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('row_number')->disabled(),
            Forms\Components\Select::make('status')
                ->options(collect(StagingStatus::cases())
                    ->mapWithKeys(fn (StagingStatus $s): array => [$s->value => $s->label()])
                    ->toArray())
                ->required(),
            Forms\Components\Textarea::make('data')
                ->label('Données mappées (JSON)')
                ->rows(18)
                ->columnSpanFull()
                ->formatStateUsing(fn ($state): string => json_encode((array) $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                ->dehydrateStateUsing(fn ($state): array => is_string($state) ? (json_decode($state, true) ?? []) : (array) $state)
                ->rules(['json']),
            Forms\Components\Textarea::make('error_message')->rows(2)->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('row_number')
            ->defaultSort('row_number')
            ->columns([
                Tables\Columns\TextColumn::make('row_number')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('data.reference')
                    ->label('SKU')
                    ->formatStateUsing(fn ($state, StagingRecord $record): string => (string) (((array) $record->data)['reference'] ?? ''))
                    ->searchable(query: fn ($query, string $search) => $query->where('data', 'like', '%"reference":"%'.$search.'%"%')),
                Tables\Columns\TextColumn::make('data.name')
                    ->label('Nom')
                    ->formatStateUsing(fn ($state, StagingRecord $record): string => (string) (((array) $record->data)['name'] ?? ''))
                    ->limit(60),
                Tables\Columns\TextColumn::make('data.price_cents')
                    ->label('Prix')
                    ->formatStateUsing(function ($state, StagingRecord $record): string {
                        $cents = ((array) $record->data)['price_cents'] ?? null;

                        return $cents === null ? '—' : number_format(((int) $cents) / 100, 2, ',', ' ').' €';
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (StagingStatus $state): string => $state->color())
                    ->formatStateUsing(fn (StagingStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('error_message')->label('Erreur')->limit(60)->tooltip(fn ($record): ?string => $record->error_message),
                Tables\Columns\TextColumn::make('lunar_product_id')->label('Produit Lunar')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(StagingStatus::cases())->mapWithKeys(fn (StagingStatus $s): array => [$s->value => $s->label()])->toArray()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('validate')
                    ->label('Marquer validées')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['status' => StagingStatus::Validated, 'validated_at' => now()])),
                Tables\Actions\BulkAction::make('skip')
                    ->label('Ignorer')
                    ->icon('heroicon-o-forward')
                    ->action(fn ($records) => $records->each->update(['status' => StagingStatus::Skipped])),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
