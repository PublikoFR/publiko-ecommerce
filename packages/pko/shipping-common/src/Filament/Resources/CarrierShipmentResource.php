<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Resources;

use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource\Pages;
use Pko\ShippingCommon\Jobs\CreateCarrierShipmentJob;
use Pko\ShippingCommon\Models\CarrierShipment;

class CarrierShipmentResource extends BaseResource
{
    protected static ?string $model = CarrierShipment::class;

    protected static ?int $navigationSort = 10;

    public static function getLabel(): string
    {
        return 'Envoi transporteur';
    }

    public static function getPluralLabel(): string
    {
        return 'Envois transporteurs';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Expédition';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-truck';
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Commande')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->url(fn (CarrierShipment $record): ?string => $record->order_id
                        ? route('filament.admin.resources.orders.view', ['record' => $record->order_id])
                        : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('carrier')
                    ->label('Transporteur')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'chronopost' => 'warning',
                        'colissimo' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('service_code')
                    ->label('Service'),
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('N° suivi')
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CarrierShipment::STATUS_CREATED => 'success',
                        CarrierShipment::STATUS_FAILED => 'danger',
                        CarrierShipment::STATUS_PENDING => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('carrier')
                    ->label('Transporteur')
                    ->options([
                        'chronopost' => 'Chronopost',
                        'colissimo' => 'Colissimo',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        CarrierShipment::STATUS_PENDING => 'En attente',
                        CarrierShipment::STATUS_CREATED => 'Créé',
                        CarrierShipment::STATUS_FAILED => 'Échec',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('download_label')
                    ->label('Télécharger étiquette')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (CarrierShipment $record): bool => $record->status === CarrierShipment::STATUS_CREATED
                        && $record->label_path !== null
                        && Storage::disk('local')->exists($record->label_path))
                    ->action(function (CarrierShipment $record) {
                        return response()->streamDownload(
                            fn () => print (Storage::disk('local')->get($record->label_path)),
                            basename($record->label_path),
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
                Tables\Actions\Action::make('retry')
                    ->label('Relancer')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (CarrierShipment $record): bool => $record->status === CarrierShipment::STATUS_FAILED)
                    ->requiresConfirmation()
                    ->action(function (CarrierShipment $record) {
                        CreateCarrierShipmentJob::dispatch(
                            $record->order_id,
                            $record->carrier,
                            (string) $record->service_code,
                        );

                        $record->update([
                            'status' => CarrierShipment::STATUS_PENDING,
                            'error_message' => null,
                        ]);

                        Notification::make()
                            ->title('Job relancé')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => Pages\ListCarrierShipments::route('/'),
            'view' => Pages\ViewCarrierShipment::route('/{record}'),
        ];
    }
}
