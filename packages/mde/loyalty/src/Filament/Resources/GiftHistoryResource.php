<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Support\Resources\BaseResource;
use Mde\Loyalty\Enums\GiftStatus;
use Mde\Loyalty\Filament\Resources\GiftHistoryResource\Pages;
use Mde\Loyalty\Models\GiftHistory;

class GiftHistoryResource extends BaseResource
{
    protected static ?string $model = GiftHistory::class;

    protected static ?int $navigationSort = 51;

    public static function getLabel(): string
    {
        return 'Cadeau débloqué';
    }

    public static function getPluralLabel(): string
    {
        return 'Cadeaux débloqués';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-gift';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = GiftHistory::query()->where('admin_viewed', false)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')
                ->label('Statut')
                ->options(GiftStatus::options())
                ->required(),
            Forms\Components\Textarea::make('admin_notes')
                ->label('Notes admin')
                ->rows(4),
        ]);
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->defaultSort('unlocked_at', 'desc')
            ->columns([
                Tables\Columns\IconColumn::make('admin_viewed')
                    ->label('Vu')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-bell-alert')
                    ->falseColor('warning'),
                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn (GiftHistory $r) => trim(($r->customer?->first_name ?? '').' '.($r->customer?->last_name ?? '')))
                    ->searchable(['first_name', 'last_name']),
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
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(GiftStatus::options()),
                Tables\Filters\TernaryFilter::make('admin_viewed')
                    ->label('Vu par admin'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Statut / Notes')
                    ->mutateFormDataUsing(function (array $data): array {
                        return $data;
                    })
                    ->after(function (GiftHistory $record): void {
                        $record->update([
                            'admin_viewed' => true,
                            'status_updated_at' => now(),
                        ]);
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('mark_all_viewed')
                    ->label('Tout marquer comme vu')
                    ->icon('heroicon-o-check')
                    ->action(fn () => GiftHistory::query()->where('admin_viewed', false)->update(['admin_viewed' => true])),
            ]);
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => Pages\ListGiftHistory::route('/'),
            'edit' => Pages\EditGiftHistory::route('/{record}/edit'),
        ];
    }
}
