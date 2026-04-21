<?php

declare(strict_types=1);

namespace Pko\ShippingColissimo\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Pko\ShippingColissimo\Data\PublicTariffs2026;
use Pko\ShippingCommon\Filament\Pages\AbstractCarrierConfigPage;
use Pko\ShippingCommon\Models\CarrierGridBracket;
use Pko\ShippingCommon\Models\CarrierService;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;

class ColissimoConfig extends AbstractCarrierConfigPage
{
    protected static string $view = 'pko-shipping-common::pages.carrier-config';

    protected static ?int $navigationSort = 21;

    protected function carrierCode(): string
    {
        return 'colissimo';
    }

    protected static function navigationLabel(): ?string
    {
        return 'Colissimo';
    }

    protected function getHeaderActions(): array
    {
        $actions = parent::getHeaderActions();

        $year = PublicTariffs2026::YEAR;
        $actions[] = Action::make('loadPublicTariffs')
            ->label("Charger les tarifs publics {$year}")
            ->icon('heroicon-o-arrow-down-tray')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription("Remplace services + grille par la baseline publique La Poste {$year}. Les valeurs actuelles sont écrasées.")
            ->action(function () use ($year): void {
                DB::transaction(function (): void {
                    CarrierService::query()->where('carrier_code', 'colissimo')->delete();
                    foreach (PublicTariffs2026::SERVICES as $s) {
                        CarrierService::create(array_merge($s, ['carrier_code' => 'colissimo']));
                    }

                    CarrierGridBracket::query()->where('carrier_code', 'colissimo')->delete();
                    foreach (PublicTariffs2026::GRID as $b) {
                        CarrierGridBracket::create(array_merge($b, ['carrier_code' => 'colissimo']));
                    }
                });

                app(CarrierServiceRepository::class)->flushCache('colissimo');
                app(CarrierGridRepository::class)->flushCache('colissimo');

                Notification::make()
                    ->success()
                    ->title("Tarifs publics {$year} chargés")
                    ->body('Rechargez la page pour voir les nouvelles valeurs dans le formulaire.')
                    ->send();
            });

        return $actions;
    }
}
