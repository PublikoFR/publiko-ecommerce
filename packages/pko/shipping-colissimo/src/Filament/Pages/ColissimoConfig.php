<?php

declare(strict_types=1);

namespace Pko\ShippingColissimo\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BasePage;
use Pko\Secrets\Facades\Secrets;
use Pko\Secrets\Filament\Forms\SecretsFormSchema;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Throwable;

class ColissimoConfig extends BasePage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Expédition';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationLabel(): string
    {
        return __('pko-shipping-colissimo::admin.config.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pko-shipping-colissimo::admin.config.title');
    }

    protected static string $view = 'pko-shipping-colissimo::pages.colissimo-config';

    protected static ?int $navigationSort = 21;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(SecretsFormSchema::initialData('colissimo'));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                SecretsFormSchema::make('colissimo', [
                    'contract_number' => 'Numéro de contrat',
                    'password' => 'Mot de passe',
                ], heading: 'Credentials Colissimo'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        SecretsFormSchema::save('colissimo', $state);

        Notification::make()
            ->success()
            ->title(__('pko-secrets::secrets.saved'))
            ->send();
    }

    public function getCurrentSource(): string
    {
        return Secrets::source('colissimo');
    }

    public function getContractNumber(): ?string
    {
        return Secrets::get('colissimo', 'contract_number') ?: config('colissimo.credentials.contract_number');
    }

    public function hasContractNumber(): bool
    {
        return filled($this->getContractNumber());
    }

    public function hasPassword(): bool
    {
        return filled(Secrets::get('colissimo', 'password') ?: config('colissimo.credentials.password'));
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
        $pass = (string) (Secrets::get('colissimo', 'password') ?: config('colissimo.credentials.password', ''));
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
                                ->body('Renseignez COLISSIMO_CONTRACT et COLISSIMO_PASSWORD dans .env ou passez le module en mode base de données.')
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
