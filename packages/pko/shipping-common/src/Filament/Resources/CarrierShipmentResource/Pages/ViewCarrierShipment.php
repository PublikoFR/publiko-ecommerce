<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource\Pages;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Lunar\Admin\Support\Pages\BaseViewRecord;
use Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource;
use Pko\ShippingCommon\Models\CarrierShipment;

class ViewCarrierShipment extends BaseViewRecord
{
    protected static string $resource = CarrierShipmentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Envoi')->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('order_id')->label('Commande')->formatStateUsing(fn ($state) => "#{$state}"),
                TextEntry::make('carrier')->label('Transporteur')->badge(),
                TextEntry::make('service_code')->label('Service'),
                TextEntry::make('status')->label('Statut')->badge()->color(fn (string $state): string => match ($state) {
                    CarrierShipment::STATUS_CREATED => 'success',
                    CarrierShipment::STATUS_FAILED => 'danger',
                    default => 'gray',
                }),
                TextEntry::make('tracking_number')->label('N° de suivi')->copyable()->placeholder('—'),
                TextEntry::make('label_path')->label('Étiquette (chemin)')->placeholder('—'),
                TextEntry::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i'),
                TextEntry::make('updated_at')->label('MàJ le')->dateTime('d/m/Y H:i'),
            ])->columns(2),
            Section::make('Erreur')
                ->visible(fn (CarrierShipment $record): bool => $record->status === CarrierShipment::STATUS_FAILED)
                ->schema([
                    TextEntry::make('error_message')->label('Message')->columnSpanFull(),
                ]),
            Section::make('Payload envoyé')
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextEntry::make('payload_sent')
                        ->hiddenLabel()
                        ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—')
                        ->columnSpanFull(),
                ]),
            Section::make('Réponse reçue')
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextEntry::make('response_received')
                        ->hiddenLabel()
                        ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
