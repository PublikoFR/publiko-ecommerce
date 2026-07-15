<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Lunar\Admin\Support\Pages\BasePage;
use Pko\Secrets\Facades\Secrets;
use Pko\Secrets\Filament\Forms\SecretsFormSchema;
use Pko\StorefrontCms\Models\Setting;

class SireneConfig extends BasePage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Vérification SIRET';

    protected static ?string $title = 'Vérification SIRET (INSEE Sirene)';

    protected static string $view = 'filament.pages.sirene-config';

    protected static ?int $navigationSort = 20;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(array_merge(
            ['enabled' => $this->isEnabled()],
            SecretsFormSchema::initialData('insee'),
        ));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Activation')
                    ->description("Active ou désactive la vérification automatique du numéro SIRET à l'inscription des professionnels.")
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Vérification SIRET activée')
                            ->helperText('Si désactivée, les inscriptions pro sont acceptées sans contrôle INSEE (statut « en attente » de validation manuelle).'),
                    ]),
                SecretsFormSchema::make('insee', [
                    'consumer_key' => 'Consumer key (clé API)',
                    'consumer_secret' => 'Consumer secret (clé secrète)',
                ], heading: 'Clés API INSEE Sirene'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::set('sirene.enabled', (bool) ($state['enabled'] ?? false));
        SecretsFormSchema::save('insee', $state);

        Notification::make()
            ->success()
            ->title(__('pko-secrets::secrets.saved'))
            ->send();
    }

    public function isEnabled(): bool
    {
        return (bool) brand_setting('sirene.enabled', config('customer-auth.sirene.enabled'));
    }

    public function getConsumerKey(): ?string
    {
        return Secrets::get('insee', 'consumer_key') ?: config('customer-auth.sirene.consumer_key');
    }

    public function getConsumerSecret(): ?string
    {
        return Secrets::get('insee', 'consumer_secret') ?: config('customer-auth.sirene.consumer_secret');
    }

    public function hasConsumerKey(): bool
    {
        return filled($this->getConsumerKey());
    }

    public function hasConsumerSecret(): bool
    {
        return filled($this->getConsumerSecret());
    }

    public function isFullyConfigured(): bool
    {
        return $this->hasConsumerKey() && $this->hasConsumerSecret();
    }

    public function getCurrentSource(): string
    {
        return Secrets::source('insee');
    }

    public function getMaskedSecret(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        if (strlen($value) <= 8) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 4).str_repeat('•', 12).substr($value, -4);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Tester la connexion INSEE')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->disabled(fn (): bool => ! $this->isFullyConfigured())
                ->action(function (): void {
                    $key = (string) $this->getConsumerKey();
                    $secret = (string) $this->getConsumerSecret();

                    if ($key === '' || $secret === '') {
                        Notification::make()
                            ->danger()
                            ->title('Clés INSEE manquantes')
                            ->body('Renseignez la consumer key et le consumer secret (via .env ou en mode base de données).')
                            ->send();

                        return;
                    }

                    try {
                        $response = Http::asForm()
                            ->withBasicAuth($key, $secret)
                            ->timeout(8)
                            ->post('https://api.insee.fr/token', ['grant_type' => 'client_credentials']);

                        if ($response->successful() && filled($response->json('access_token'))) {
                            Notification::make()
                                ->success()
                                ->title('Connexion INSEE réussie')
                                ->body('Un jeton OAuth a été obtenu — les clés API sont valides.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->danger()
                            ->title('Échec de connexion INSEE')
                            ->body('Réponse HTTP '.$response->status().'. Vérifiez la consumer key / consumer secret.')
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
