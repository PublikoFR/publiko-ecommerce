<?php

declare(strict_types=1);

namespace Pko\ShippingColissimo\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lunar\Admin\Support\Pages\BasePage;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Throwable;

class ColissimoConfig extends BasePage
{
    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Colissimo';

    protected static ?string $title = 'Configuration Colissimo';

    protected static string $view = 'mde-shipping-colissimo::pages.colissimo-config';

    protected static ?int $navigationSort = 21;

    public function getContractNumber(): ?string
    {
        return config('colissimo.credentials.contract_number');
    }

    public function hasContractNumber(): bool
    {
        return filled($this->getContractNumber());
    }

    public function hasPassword(): bool
    {
        return filled(config('colissimo.credentials.password'));
    }

    public function isConfigured(): bool
    {
        return $this->hasContractNumber() && $this->hasPassword();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getServices(): array
    {
        $services = [];
        foreach ((array) config('colissimo.services', []) as $code => $service) {
            $services[] = [
                'code' => (string) $code,
                'label' => (string) ($service['label'] ?? $code),
                'enabled' => (bool) ($service['enabled'] ?? false),
            ];
        }

        return $services;
    }

    /**
     * @return array<int, array<string, int|float>>
     */
    public function getGrid(): array
    {
        return (array) config('colissimo.grid', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getShipper(): array
    {
        return (array) config('colissimo.shipper', []);
    }

    public function getMaskedPassword(): string
    {
        $pass = (string) config('colissimo.credentials.password', '');
        if ($pass === '') {
            return '—';
        }

        return str_repeat('•', max(4, strlen($pass) - 2)).substr($pass, -2);
    }

    public function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testCredentials')
                ->label('Tester les credentials')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->disabled(fn (): bool => ! $this->isConfigured())
                ->action(function (): void {
                    try {
                        /** @var CarrierClient $client */
                        $client = app('pko.shipping.carrier.colissimo');

                        if ($client->testCredentials()) {
                            Notification::make()
                                ->success()
                                ->title('Credentials valides')
                                ->body('Les identifiants Colissimo sont présents.')
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Credentials manquants')
                                ->body('Renseignez COLISSIMO_CONTRACT et COLISSIMO_PASSWORD dans .env.')
                                ->send();
                        }
                    } catch (Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Erreur')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
