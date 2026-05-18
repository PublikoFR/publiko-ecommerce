<?php

declare(strict_types=1);

namespace Pko\Pennylane\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Lunar\Admin\Support\Pages\BasePage;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Secrets\Facades\Secrets;
use Pko\Secrets\Filament\Forms\SecretsFormSchema;

class PennylaneConfig extends BasePage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-euro';

    protected static string $view = 'pko-pennylane::pages.config';

    protected static ?int $navigationSort = 80;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('pko-pennylane::admin.config.nav');
    }

    public function getTitle(): string
    {
        return __('pko-pennylane::admin.config.title');
    }

    public function mount(): void
    {
        $secrets = SecretsFormSchema::initialData('pennylane');

        $this->form->fill([
            ...$secrets,
            'trigger_on_status' => config('pennylane.trigger_on_status'),
            'auto_credit_note_on_refund' => (bool) config('pennylane.auto_credit_note_on_refund'),
            'default_payment_deadline_days' => (int) config('pennylane.default_payment_deadline_days'),
            'default_language' => config('pennylane.default_language'),
        ]);
    }

    public function form(Form $form): Form
    {
        $statuses = array_keys(config('lunar.orders.statuses', []));

        return $form
            ->schema([
                SecretsFormSchema::make('pennylane', [
                    'api_token' => __('pko-pennylane::admin.config.api_token'),
                    'invoice_template_id' => __('pko-pennylane::admin.config.template_id'),
                ], heading: __('pko-pennylane::admin.config.credentials')),

                TextInput::make('default_payment_deadline_days')
                    ->label(__('pko-pennylane::admin.config.deadline_days'))
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(365),

                Select::make('trigger_on_status')
                    ->label(__('pko-pennylane::admin.config.trigger_status'))
                    ->options(array_combine($statuses, $statuses))
                    ->helperText(__('pko-pennylane::admin.config.trigger_status_help'))
                    ->searchable(),

                Toggle::make('auto_credit_note_on_refund')
                    ->label(__('pko-pennylane::admin.config.auto_credit_note'))
                    ->helperText(__('pko-pennylane::admin.config.auto_credit_note_help')),

                Select::make('default_language')
                    ->label(__('pko-pennylane::admin.config.language'))
                    ->options([
                        'fr' => 'Français',
                        'en' => 'English',
                    ])
                    ->default('fr'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        SecretsFormSchema::save('pennylane', $state);

        $runtime = config('pennylane');
        $runtime['trigger_on_status'] = $state['trigger_on_status'] ?? $runtime['trigger_on_status'];
        $runtime['auto_credit_note_on_refund'] = (bool) ($state['auto_credit_note_on_refund'] ?? false);
        $runtime['default_payment_deadline_days'] = (int) ($state['default_payment_deadline_days'] ?? 0);
        $runtime['default_language'] = $state['default_language'] ?? $runtime['default_language'];
        config(['pennylane' => $runtime]);

        Notification::make()
            ->success()
            ->title(__('pko-pennylane::admin.config.saved'))
            ->send();
    }

    public function getApiToken(): ?string
    {
        return Secrets::get('pennylane', 'api_token') ?: config('pennylane.api_token');
    }

    public function getTemplateId(): ?string
    {
        return Secrets::get('pennylane', 'invoice_template_id') ?: config('pennylane.customer_invoice_template_id');
    }

    public function hasApiToken(): bool
    {
        return filled($this->getApiToken());
    }

    public function hasTemplate(): bool
    {
        return filled($this->getTemplateId());
    }

    public function isFullyConfigured(): bool
    {
        return $this->hasApiToken() && $this->hasTemplate();
    }

    public function getMaskedToken(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        $len = strlen($value);
        if ($len <= 11) {
            return str_repeat('•', $len);
        }

        return substr($value, 0, 7).str_repeat('•', 12).substr($value, -4);
    }

    public function getCurrentSource(): string
    {
        return Secrets::source('pennylane');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label(__('pko-pennylane::admin.config.test'))
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->disabled(fn (): bool => ! $this->hasApiToken())
                ->action(function (): void {
                    try {
                        $client = app(PennylaneClient::class);
                        $response = $client->get('/me');
                        $body = (array) $response->json();

                        Notification::make()
                            ->success()
                            ->title(__('pko-pennylane::admin.config.test_success'))
                            ->body('Utilisateur Pennylane : '.($body['email'] ?? $body['id'] ?? 'inconnu'))
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title(__('pko-pennylane::admin.config.test_failure'))
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
