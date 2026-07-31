<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BasePage;
use Pko\Secrets\Facades\Secrets;
use Pko\Secrets\Filament\Forms\SecretsFormSchema;
use Pko\ShippingCommon\Carriers\CarrierDefinition;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Filament\Clusters\Shipping;
use Pko\ShippingCommon\Pricing\LivePricingResolver;
use Pko\ShippingCommon\Pricing\PricingMode;
use Pko\ShippingCommon\Pricing\PricingModeResolver;
use Throwable;

/**
 * Reusable Filament page for PKO carrier configuration.
 *
 * Subclasses declare carrierCode() and (optionally) override the navigation
 * labels / title. Parent renders :
 *   - Credentials section (via SecretsFormSchema, toggle env/DB)
 *   - Pricing mode section (carriers supporting live pricing)
 *   - Test credentials action.
 *
 * Les services et la grille tarifaire sont gérés par deux tables CRUD Livewire
 * embarquées dans la vue (CarrierServicesTable / CarrierGridTable), une page
 * Filament ne pouvant héberger qu'une seule table.
 */
abstract class AbstractCarrierConfigPage extends BasePage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = Shipping::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Transporteurs';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    abstract protected function carrierCode(): string;

    protected function carrierDefinition(): CarrierDefinition
    {
        $def = app(CarrierRegistry::class)->get($this->carrierCode());
        if ($def === null) {
            throw new \RuntimeException("Carrier [{$this->carrierCode()}] is not registered.");
        }

        return $def;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Configuration '.$this->carrierDefinition()->displayName;
    }

    public static function getNavigationLabel(): string
    {
        return static::navigationLabel() ?? class_basename(static::class);
    }

    protected static function navigationLabel(): ?string
    {
        return null;
    }

    public function mount(): void
    {
        $this->form->fill(array_merge(
            SecretsFormSchema::initialData($this->carrierCode()),
            [
                'pricing_mode' => app(PricingModeResolver::class)->getFor($this->carrierCode())->value,
            ],
        ));
    }

    public function form(Form $form): Form
    {
        $def = $this->carrierDefinition();

        $pricingSection = $def->supportsLive
            ? Section::make('Mode de tarification')
                ->description('Choisir comment les tarifs sont calculés au checkout. Le mode live requiert des credentials contractuels valides.')
                ->schema([
                    ToggleButtons::make('pricing_mode')
                        ->label(null)
                        ->options([
                            PricingMode::GRID->value => PricingMode::GRID->label(),
                            PricingMode::LIVE_WITH_FALLBACK->value => PricingMode::LIVE_WITH_FALLBACK->label(),
                            PricingMode::LIVE_ONLY->value => PricingMode::LIVE_ONLY->label(),
                        ])
                        ->icons([
                            PricingMode::GRID->value => 'heroicon-o-table-cells',
                            PricingMode::LIVE_WITH_FALLBACK->value => 'heroicon-o-bolt',
                            PricingMode::LIVE_ONLY->value => 'heroicon-o-rocket-launch',
                        ])
                        ->inline()
                        ->required()
                        ->helperText('Grille = rapide et toujours dispo. Live+fallback = prix frais avec repli grille si API down. Live strict = prix frais uniquement, pas de tarif si API down.'),
                ])
            : null;

        return $form
            ->schema(array_values(array_filter([
                SecretsFormSchema::make(
                    $this->carrierCode(),
                    $def->credentialLabels,
                    heading: "Credentials {$def->displayName}",
                ),
                $pricingSection,
            ])))
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $carrier = $this->carrierCode();

        SecretsFormSchema::save($carrier, $state);

        if ($this->carrierDefinition()->supportsLive && isset($state['pricing_mode'])) {
            $mode = PricingMode::tryFrom((string) $state['pricing_mode']) ?? PricingMode::GRID;
            app(PricingModeResolver::class)->setFor($carrier, $mode);
            if ($mode === PricingMode::GRID) {
                app(LivePricingResolver::class)->flushCache($carrier);
            }
        }

        Notification::make()
            ->success()
            ->title('Configuration enregistrée')
            ->send();
    }

    public function getCurrentSource(): string
    {
        return Secrets::source($this->carrierCode());
    }

    public function isConfigured(): bool
    {
        foreach ($this->carrierDefinition()->credentialLabels as $key => $_label) {
            if ($key === 'sub_account') {
                continue;
            }
            if (blank(Secrets::get($this->carrierCode(), $key) ?: config($this->carrierCode().'.credentials.'.$key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Code transporteur exposé à la vue (tables Livewire embarquées).
     */
    public function getCarrierCode(): string
    {
        return $this->carrierCode();
    }

    /**
     * @return array<string, mixed>
     */
    public function getShipper(): array
    {
        return (array) config($this->carrierCode().'.shipper', []);
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->carrierDefinition()->supportsLive) {
            $actions[] = Action::make('flushLiveCache')
                ->label('Vider le cache tarifs live')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Les prochains checkout déclencheront à nouveau un appel API pour chaque poids/zone.')
                ->action(function (): void {
                    app(LivePricingResolver::class)->flushCache($this->carrierCode());

                    Notification::make()
                        ->success()
                        ->title('Cache tarifs vidé')
                        ->send();
                });
        }

        $actions[] = Action::make('testCredentials')
            ->label('Tester les credentials')
            ->icon('heroicon-o-bolt')
            ->color('primary')
            ->disabled(fn (): bool => ! $this->isConfigured())
            ->action(function (): void {
                try {
                    /** @var CarrierClient $client */
                    $client = app($this->carrierDefinition()->clientServiceId);

                    if ($client->testCredentials()) {
                        Notification::make()
                            ->success()
                            ->title('Credentials valides')
                            ->body("Les identifiants {$this->carrierDefinition()->displayName} sont présents.")
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Credentials manquants')
                            ->body('Renseignez les credentials via le formulaire (.env ou base de données).')
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
            });

        return $actions;
    }
}
