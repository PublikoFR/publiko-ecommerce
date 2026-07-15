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
                    'api_key' => 'Clé API (portail INSEE)',
                ], heading: 'Clé API INSEE Sirene'),
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

    public function getApiKey(): ?string
    {
        return Secrets::get('insee', 'api_key') ?: config('customer-auth.sirene.api_key');
    }

    public function hasApiKey(): bool
    {
        return filled($this->getApiKey());
    }

    public function isFullyConfigured(): bool
    {
        return $this->hasApiKey();
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
                    $key = (string) $this->getApiKey();

                    if ($key === '') {
                        Notification::make()
                            ->danger()
                            ->title('Clé API INSEE manquante')
                            ->body('Renseignez la clé API (via .env ou en mode base de données).')
                            ->send();

                        return;
                    }

                    try {
                        $header = (string) config('customer-auth.sirene.api_key_header', 'X-INSEE-Api-Key-Integration');
                        $baseUrl = rtrim((string) config('customer-auth.sirene.base_url'), '/');

                        // Requête authentifiée sur un SIRET de contrôle : 200/404 =
                        // clé valide (404 = SIRET introuvable mais auth OK), 401/403 = clé invalide.
                        $response = Http::withHeaders([$header => $key])
                            ->timeout(8)
                            ->acceptJson()
                            ->get($baseUrl.'/siret/00000000000000');

                        if (in_array($response->status(), [200, 404], true)) {
                            Notification::make()
                                ->success()
                                ->title('Connexion INSEE réussie')
                                ->body('La clé API est valide (API Sirene joignable et authentifiée).')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->danger()
                            ->title('Échec de connexion INSEE')
                            ->body('Réponse HTTP '.$response->status().'. Vérifiez la clé API.')
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
