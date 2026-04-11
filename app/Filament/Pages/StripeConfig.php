<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lunar\Admin\Support\Pages\BasePage;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeConfig extends BasePage
{
    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Stripe';

    protected static ?string $title = 'Configuration Stripe';

    protected static string $view = 'filament.pages.stripe-config';

    protected static ?int $navigationSort = 10;

    public function getPublicKey(): ?string
    {
        return config('services.stripe.public_key');
    }

    public function getSecretKey(): ?string
    {
        return config('services.stripe.key');
    }

    public function getWebhookSecret(): ?string
    {
        return config('services.stripe.webhooks.lunar');
    }

    public function getWebhookUrl(): string
    {
        return url(config('lunar.stripe.webhook_path', 'stripe/webhook'));
    }

    public function hasPublicKey(): bool
    {
        return filled($this->getPublicKey());
    }

    public function hasSecretKey(): bool
    {
        return filled($this->getSecretKey());
    }

    public function hasWebhookSecret(): bool
    {
        return filled($this->getWebhookSecret());
    }

    public function isFullyConfigured(): bool
    {
        return $this->hasPublicKey()
            && $this->hasSecretKey()
            && $this->hasWebhookSecret();
    }

    public function getMaskedSecret(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return substr($value, 0, 7).str_repeat('•', 12).substr($value, -4);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Tester la connexion Stripe')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->disabled(fn (): bool => ! $this->hasSecretKey())
                ->action(function (): void {
                    $secret = $this->getSecretKey();

                    if (blank($secret)) {
                        Notification::make()
                            ->danger()
                            ->title('Clé secrète Stripe manquante')
                            ->body('Renseignez STRIPE_SECRET dans le fichier .env.')
                            ->send();

                        return;
                    }

                    try {
                        $client = new StripeClient($secret);
                        $balance = $client->balance->retrieve();

                        $livemode = $balance->livemode ?? false;
                        $mode = $livemode ? 'LIVE' : 'TEST';

                        Notification::make()
                            ->success()
                            ->title('Connexion Stripe réussie')
                            ->body("Compte en mode {$mode}. L'API Stripe répond correctement.")
                            ->send();
                    } catch (ApiErrorException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Échec de connexion Stripe')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Erreur inattendue')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
