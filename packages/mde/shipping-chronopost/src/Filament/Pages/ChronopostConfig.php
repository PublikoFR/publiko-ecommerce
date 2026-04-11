<?php

declare(strict_types=1);

namespace Mde\ShippingChronopost\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lunar\Admin\Support\Pages\BasePage;
use Mde\ShippingCommon\Contracts\CarrierClient;
use Throwable;

class ChronopostConfig extends BasePage
{
    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Chronopost';

    protected static ?string $title = 'Configuration Chronopost';

    protected static string $view = 'mde-shipping-chronopost::pages.chronopost-config';

    protected static ?int $navigationSort = 20;

    public function getAccount(): ?string
    {
        return config('chronopost.credentials.account');
    }

    public function getSubAccount(): ?string
    {
        return config('chronopost.credentials.sub_account');
    }

    public function hasAccount(): bool
    {
        return filled($this->getAccount());
    }

    public function hasPassword(): bool
    {
        return filled(config('chronopost.credentials.password'));
    }

    public function isConfigured(): bool
    {
        return $this->hasAccount() && $this->hasPassword();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getServices(): array
    {
        $services = [];
        foreach ((array) config('chronopost.services', []) as $code => $service) {
            $services[] = [
                'code' => (string) $code,
                'label' => (string) ($service['label'] ?? $code),
                'enabled' => (bool) ($service['enabled'] ?? false),
            ];
        }

        return $services;
    }

    /**
     * @return array<int, array<string, int>>
     */
    public function getGrid(): array
    {
        return (array) config('chronopost.grid', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getShipper(): array
    {
        return (array) config('chronopost.shipper', []);
    }

    public function getMaskedPassword(): string
    {
        $pass = (string) config('chronopost.credentials.password', '');
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
                        $client = app('mde.shipping.carrier.chronopost');

                        if ($client->testCredentials()) {
                            Notification::make()
                                ->success()
                                ->title('Credentials valides')
                                ->body('Les identifiants Chronopost sont présents.')
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Credentials manquants')
                                ->body('Renseignez CHRONOPOST_ACCOUNT et CHRONOPOST_PASSWORD dans .env.')
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
