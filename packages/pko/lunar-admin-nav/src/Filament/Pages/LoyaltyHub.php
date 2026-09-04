<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Pko\Loyalty\Models\Setting;

class LoyaltyHub extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static string $view = 'admin-nav::pages.loyalty-hub';

    protected static ?string $slug = 'fidelite';

    #[Url(as: 'tab')]
    public string $activeTab = 'tiers';

    /** @var array<string, mixed> */
    public array $settingsData = [];

    public static function getNavigationLabel(): string
    {
        return __('admin-nav::admin.hubs.loyalty.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin-nav::admin.hubs.loyalty.title');
    }

    public function mount(): void
    {
        $this->settingsForm->fill([
            'points_ratio' => Setting::get('points_ratio', (string) config('loyalty.default_ratio', 1)),
        ]);
    }

    protected function getForms(): array
    {
        return [
            'settingsForm',
        ];
    }

    public function settingsForm(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('points_ratio')
                    ->label(__('admin-nav::admin.hubs.loyalty.fields.points_ratio.label'))
                    ->helperText(__('admin-nav::admin.hubs.loyalty.fields.points_ratio.help'))
                    ->required()
                    ->numeric()
                    ->minValue(0.01),
            ])
            ->statePath('settingsData');
    }

    public function saveSettings(): void
    {
        $data = $this->settingsForm->getState();

        Setting::set('points_ratio', $data['points_ratio']);

        Notification::make()
            ->success()
            ->title(__('admin-nav::admin.hubs.loyalty.settings.saved'))
            ->send();
    }

    /**
     * @return array<string, string>
     */
    public function getTabs(): array
    {
        return [
            'tiers' => __('admin-nav::admin.hubs.loyalty.tabs.tiers'),
            'gifts' => __('admin-nav::admin.hubs.loyalty.tabs.gifts'),
            'points' => __('admin-nav::admin.hubs.loyalty.tabs.points'),
            'settings' => __('admin-nav::admin.hubs.loyalty.tabs.settings'),
        ];
    }
}
