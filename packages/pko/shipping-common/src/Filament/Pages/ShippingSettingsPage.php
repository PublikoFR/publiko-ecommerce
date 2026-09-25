<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Pages;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BasePage;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Filament\Clusters\Shipping;
use Pko\ShippingCommon\Models\CarrierService;
use Pko\ShippingCommon\Models\ShippingSurcharge;
use Pko\ShippingCommon\Settings\ShippingSettings;
use Pko\StorefrontCms\Models\Setting;

class ShippingSettingsPage extends BasePage implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = Shipping::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

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
            // Le joker « tous les services » n'est pas une option du select : on le
            // représente par un champ vide (cf. helperText), et `save()` le rétablit.
            'services' => array_values(array_diff(
                ShippingSettings::francoServices(),
                [ShippingSettings::FRANCO_ALL_SERVICES],
            )),
            'basis' => ShippingSettings::francoBasis(),
            'tax_price_base' => ShippingSettings::taxPriceBase(),
            'tax_display' => ShippingSettings::taxDisplay(),
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

                        Select::make('services')
                            ->label(__('pko-shipping-common::admin.settings.services'))
                            ->helperText(__('pko-shipping-common::admin.settings.services_help'))
                            ->placeholder(__('pko-shipping-common::admin.settings.services_placeholder'))
                            ->multiple()
                            ->searchable()
                            ->native(false)
                            ->options(fn (): array => $this->serviceOptions()),

                        ToggleButtons::make('basis')
                            ->label(__('pko-shipping-common::admin.settings.basis'))
                            ->helperText(__('pko-shipping-common::admin.settings.basis_help'))
                            ->options([
                                'eligible_only' => __('pko-shipping-common::admin.settings.basis_eligible_only'),
                                'cart_total' => __('pko-shipping-common::admin.settings.basis_cart_total'),
                            ])
                            ->icons([
                                'eligible_only' => 'heroicon-o-shield-check',
                                'cart_total' => 'heroicon-o-shopping-cart',
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
                                'ht' => __('pko-shipping-common::admin.settings.tax_price_base_ht'),
                                'ttc' => __('pko-shipping-common::admin.settings.tax_price_base_ttc'),
                            ])
                            ->required(),

                        Select::make('tax_display')
                            ->label(__('pko-shipping-common::admin.settings.tax_display'))
                            ->helperText(__('pko-shipping-common::admin.settings.tax_display_help'))
                            ->options([
                                'both' => __('pko-shipping-common::admin.settings.tax_display_both'),
                                'ht' => __('pko-shipping-common::admin.settings.tax_display_ht'),
                                'ttc' => __('pko-shipping-common::admin.settings.tax_display_ttc'),
                            ])
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Section « Suppléments transport » : CRUD en modale, sous les réglages.
     * Pas de Resource dédiée — une liste courte (une dizaine de lignes) que
     * l'on règle en même temps que le franco.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(ShippingSurcharge::query())
            ->heading(__('pko-shipping-common::admin.surcharge.nav'))
            ->description(__('pko-shipping-common::admin.surcharge.section_help'))
            ->columns([
                TextColumn::make('label')
                    ->label(__('pko-shipping-common::admin.surcharge.label_field'))
                    ->description(fn (ShippingSurcharge $record): string => (string) $record->code)
                    ->searchable(['label', 'code']),
                TextColumn::make('mode')
                    ->label(__('pko-shipping-common::admin.surcharge.mode'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'auto' => 'Auto',
                        'rebill' => 'Refacturé',
                        default => 'Sur devis',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'auto' => 'success',
                        'rebill' => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('amount_cents')
                    ->label(__('pko-shipping-common::admin.surcharge.amount_cents'))
                    ->formatStateUsing(fn (?int $state): string => $state !== null
                        ? number_format($state / 100, 2, ',', ' ').' €'
                        : '—')
                    ->placeholder('—'),
                ToggleColumn::make('enabled')
                    ->label(__('pko-shipping-common::admin.surcharge.enabled')),
            ])
            ->defaultSort('code')
            ->paginated(false)
            ->headerActions([
                CreateAction::make()
                    ->label(__('pko-shipping-common::admin.surcharge.create'))
                    ->model(ShippingSurcharge::class)
                    ->form(self::surchargeFormSchema()),
            ])
            ->actions([
                EditAction::make()->form(self::surchargeFormSchema()),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('pko-shipping-common::admin.surcharge.empty'));
    }

    /**
     * @return array<int, Component>
     */
    public static function surchargeFormSchema(): array
    {
        return [
            TextInput::make('code')
                ->label(__('pko-shipping-common::admin.surcharge.code'))
                ->required()
                ->unique(ShippingSurcharge::class, 'code', ignoreRecord: true)
                ->maxLength(64)
                ->helperText('Identifiant technique (snake_case, unique).'),

            TextInput::make('label')
                ->label(__('pko-shipping-common::admin.surcharge.label_field'))
                ->required()
                ->maxLength(255),

            Select::make('mode')
                ->label(__('pko-shipping-common::admin.surcharge.mode'))
                ->options([
                    'auto' => __('pko-shipping-common::admin.surcharge.mode_auto'),
                    'quote' => __('pko-shipping-common::admin.surcharge.mode_quote'),
                    'rebill' => __('pko-shipping-common::admin.surcharge.mode_rebill'),
                ])
                ->required()
                ->default('quote')
                ->helperText(fn (Get $get): string => match ($get('mode')) {
                    'auto' => __('pko-shipping-common::admin.surcharge.mode_auto_help'),
                    'rebill' => __('pko-shipping-common::admin.surcharge.mode_rebill_help'),
                    default => __('pko-shipping-common::admin.surcharge.mode_quote_help'),
                })
                ->live(),

            TextInput::make('amount_cents')
                ->label(__('pko-shipping-common::admin.surcharge.amount_cents'))
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->suffix('€')
                ->nullable()
                // Saisi en euros, stocké en cents.
                ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format((int) $state / 100, 2, '.', '') : null)
                ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) round((float) $state * 100) : null)
                ->helperText('Laisser vide si le montant est variable.'),

            Textarea::make('rule')
                ->label(__('pko-shipping-common::admin.surcharge.rule'))
                ->rows(3)
                ->nullable()
                ->helperText('Critères d\'application en JSON (optionnel).'),

            Toggle::make('enabled')
                ->label(__('pko-shipping-common::admin.surcharge.enabled'))
                ->default(true),
        ];
    }

    /**
     * Options du select « services couverts par le franco », groupées par
     * transporteur. Seuls les services actifs sont proposés — un transporteur
     * dont tous les services sont désactivés (Colissimo en veille) disparaît
     * donc de la liste. Les codes déjà enregistrés mais absents de cette liste
     * (service désactivé ou supprimé) sont conservés pour ne pas les perdre
     * silencieusement à l'enregistrement.
     *
     * @return array<string, array<string, string>>
     */
    protected function serviceOptions(): array
    {
        $carriers = app(CarrierRegistry::class);

        $options = [];

        $services = CarrierService::query()
            ->where('enabled', true)
            ->orderBy('carrier_code')
            ->orderBy('sort')
            ->get();

        foreach ($services as $service) {
            $carrierCode = (string) $service->carrier_code;
            $group = $carriers->get($carrierCode)?->displayName ?? $carrierCode;

            $options[$group][(string) $service->service_code] = sprintf(
                '%s (%s)',
                (string) $service->label,
                (string) $service->service_code,
            );
        }

        $known = array_merge(...array_values(array_map('array_keys', $options)) ?: [[]]);
        $orphans = array_diff(
            (array) ShippingSettings::francoServices(),
            $known,
            [ShippingSettings::FRANCO_ALL_SERVICES], // joker : jamais une option du select
        );

        if ($orphans !== []) {
            $group = __('pko-shipping-common::admin.settings.services_orphan_group');
            foreach ($orphans as $code) {
                $options[$group][(string) $code] = (string) $code;
            }
        }

        return $options;
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $thresholdEur = (float) ($state['threshold_eur'] ?? 500.0);
        Setting::set('shipping.franco.threshold_cents', (int) round($thresholdEur * 100));
        // Aucun service coché = franco sur TOUS les services (joker). C'est le
        // comportement par défaut attendu : au-delà du seuil, le port est offert
        // quel que soit le mode choisi.
        $services = array_values(array_filter((array) ($state['services'] ?? [])));
        Setting::set(
            'shipping.franco.services',
            $services !== [] ? $services : [ShippingSettings::FRANCO_ALL_SERVICES],
        );
        Setting::set('shipping.franco.basis', $state['basis'] ?? 'eligible_only');
        Setting::set('shipping.tax.price_base', $state['tax_price_base'] ?? 'ht');
        Setting::set('shipping.tax.display', $state['tax_display'] ?? 'both');

        Notification::make()
            ->success()
            ->title(__('pko-shipping-common::admin.settings.saved'))
            ->send();
    }
}
