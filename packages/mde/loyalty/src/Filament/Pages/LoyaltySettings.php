<?php

declare(strict_types=1);

namespace Mde\Loyalty\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Lunar\Admin\Support\Pages\BasePage;
use Mde\Loyalty\Models\Setting;

class LoyaltySettings extends BasePage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Configuration fidélité';

    protected static ?string $title = 'Configuration du programme de fidélité';

    protected static ?int $navigationSort = 53;

    protected static string $view = 'mde-loyalty::filament.pages.loyalty-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'points_ratio' => Setting::get('points_ratio', (string) config('mde-loyalty.default_ratio', 1)),
            'admin_email' => Setting::get('admin_email', (string) config('mde-loyalty.admin_email', '')),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('points_ratio')
                    ->label('Ratio €HT / point')
                    ->helperText('Montant en euros HT pour obtenir 1 point (ex: 1 = 1€HT = 1 point).')
                    ->required()
                    ->numeric()
                    ->minValue(0.01),
                TextInput::make('admin_email')
                    ->label('Email admin (notifications)')
                    ->email()
                    ->helperText('Destinataire de la notification quand un client débloque un palier. Surcharge MDE_LOYALTY_ADMIN_EMAIL.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('points_ratio', $data['points_ratio']);
        Setting::set('admin_email', $data['admin_email'] ?? '');

        Notification::make()
            ->success()
            ->title('Configuration enregistrée')
            ->send();
    }
}
