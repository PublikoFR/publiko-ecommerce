<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BasePage;
use Pko\ShippingCommon\Filament\Clusters\Shipping;
use Pko\ShippingCommon\Settings\ShippingSettings;
use Pko\StorefrontCms\Models\Setting;

class ShippingSettingsPage extends BasePage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = Shipping::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'pko-shipping-common::pages.shipping-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('pko-shipping-common::admin.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pko-shipping-common::admin.settings.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'threshold_eur' => ShippingSettings::thresholdCents() / 100,
            'services'      => ShippingSettings::francoServices(),
            'basis'         => ShippingSettings::francoBasis(),
            'tax_price_base' => ShippingSettings::taxPriceBase(),
            'tax_display'   => ShippingSettings::taxDisplay(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('pko-shipping-common::admin.settings.section_franco'))
                    ->schema([
                        TextInput::make('threshold_eur')
                            ->label(__('pko-shipping-common::admin.settings.threshold_eur'))
                            ->helperText(__('pko-shipping-common::admin.settings.threshold_eur_help'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix('€ HT')
                            ->required(),

                        TagsInput::make('services')
                            ->label(__('pko-shipping-common::admin.settings.services'))
                            ->helperText(__('pko-shipping-common::admin.settings.services_help'))
                            ->placeholder('chrono13'),

                        ToggleButtons::make('basis')
                            ->label(__('pko-shipping-common::admin.settings.basis'))
                            ->helperText(__('pko-shipping-common::admin.settings.basis_help'))
                            ->options([
                                'eligible_only' => __('pko-shipping-common::admin.settings.basis_eligible_only'),
                                'cart_total'    => __('pko-shipping-common::admin.settings.basis_cart_total'),
                            ])
                            ->icons([
                                'eligible_only' => 'heroicon-o-shield-check',
                                'cart_total'    => 'heroicon-o-shopping-cart',
                            ])
                            ->inline()
                            ->required(),
                    ]),

                Section::make(__('pko-shipping-common::admin.settings.section_tax'))
                    ->schema([
                        Select::make('tax_price_base')
                            ->label(__('pko-shipping-common::admin.settings.tax_price_base'))
                            ->helperText(__('pko-shipping-common::admin.settings.tax_price_base_help'))
                            ->options([
                                'ht'  => __('pko-shipping-common::admin.settings.tax_price_base_ht'),
                                'ttc' => __('pko-shipping-common::admin.settings.tax_price_base_ttc'),
                            ])
                            ->required(),

                        Select::make('tax_display')
                            ->label(__('pko-shipping-common::admin.settings.tax_display'))
                            ->helperText(__('pko-shipping-common::admin.settings.tax_display_help'))
                            ->options([
                                'both' => __('pko-shipping-common::admin.settings.tax_display_both'),
                                'ht'   => __('pko-shipping-common::admin.settings.tax_display_ht'),
                                'ttc'  => __('pko-shipping-common::admin.settings.tax_display_ttc'),
                            ])
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $thresholdEur = (float) ($state['threshold_eur'] ?? 500.0);
        Setting::set('shipping.franco.threshold_cents', (int) round($thresholdEur * 100));
        Setting::set('shipping.franco.services', $state['services'] ?? ['chrono13']);
        Setting::set('shipping.franco.basis', $state['basis'] ?? 'eligible_only');
        Setting::set('shipping.tax.price_base', $state['tax_price_base'] ?? 'ht');
        Setting::set('shipping.tax.display', $state['tax_display'] ?? 'both');

        Notification::make()
            ->success()
            ->title(__('pko-shipping-common::admin.settings.saved'))
            ->send();
    }
}
