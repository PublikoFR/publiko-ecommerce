<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Lunar\Admin\Support\Pages\BasePage;
use Pko\Secrets\Facades\Secrets;
use Pko\Secrets\Filament\Forms\SecretsFormSchema;
use Pko\ShippingCommon\Carriers\CarrierDefinition;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Models\CarrierGridBracket;
use Pko\ShippingCommon\Models\CarrierService;
use Pko\ShippingCommon\Repositories\CarrierGridRepository;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Throwable;

/**
 * Reusable Filament page for PKO carrier configuration.
 *
 * Subclasses declare carrierCode() and (optionally) override the navigation
 * labels / title. Parent renders :
 *   - Credentials section (via SecretsFormSchema, toggle env/DB)
 *   - Services section (repeater on pko_carrier_services)
 *   - Grid section (repeater on pko_carrier_grids)
 *   - Test credentials action.
 */
abstract class AbstractCarrierConfigPage extends BasePage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Expédition';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

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
                'services' => app(CarrierServiceRepository::class)->allFor($this->carrierCode()),
                'grid' => app(CarrierGridRepository::class)->forCarrier($this->carrierCode()),
            ],
        ));
    }

    public function form(Form $form): Form
    {
        $def = $this->carrierDefinition();

        return $form
            ->schema([
                SecretsFormSchema::make(
                    $this->carrierCode(),
                    $def->credentialLabels,
                    heading: "Credentials {$def->displayName}",
                ),

                Section::make('Services activés')
                    ->description('Activer/désactiver les services que ce transporteur proposera au checkout.')
                    ->schema([
                        Repeater::make('services')
                            ->label(null)
                            ->schema([
                                TextInput::make('code')->label('Code')->required()->columnSpan(1),
                                TextInput::make('label')->label('Libellé')->required()->columnSpan(2),
                                Toggle::make('enabled')->label('Actif')->columnSpan(1),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel('Ajouter un service'),
                    ]),

                Section::make('Grille tarifaire par poids')
                    ->description('Paliers tarifaires en cents (ex : 1290 = 12,90 €). Prix appliqué au premier palier dont `max_kg` ≥ poids du panier.')
                    ->schema([
                        Repeater::make('grid')
                            ->label(null)
                            ->schema([
                                TextInput::make('max_kg')->label('Max (kg)')->numeric()->required()->columnSpan(1),
                                TextInput::make('price')->label('Prix (cents)')->numeric()->required()->columnSpan(1),
                                TextInput::make('service_code')->label('Service (vide = tous)')->columnSpan(2),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->reorderable()
                            ->orderColumn('sort')
                            ->addActionLabel('Ajouter un palier'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $carrier = $this->carrierCode();

        SecretsFormSchema::save($carrier, $state);

        DB::transaction(function () use ($carrier, $state): void {
            CarrierService::query()->where('carrier_code', $carrier)->delete();
            foreach (($state['services'] ?? []) as $i => $service) {
                if (empty($service['code'])) {
                    continue;
                }
                CarrierService::create([
                    'carrier_code' => $carrier,
                    'service_code' => (string) $service['code'],
                    'label' => (string) ($service['label'] ?? $service['code']),
                    'enabled' => (bool) ($service['enabled'] ?? false),
                    'sort' => $i * 10,
                ]);
            }

            CarrierGridBracket::query()->where('carrier_code', $carrier)->delete();
            foreach (($state['grid'] ?? []) as $i => $bracket) {
                if (! isset($bracket['max_kg'], $bracket['price'])) {
                    continue;
                }
                CarrierGridBracket::create([
                    'carrier_code' => $carrier,
                    'service_code' => ! empty($bracket['service_code']) ? (string) $bracket['service_code'] : null,
                    'max_kg' => (int) $bracket['max_kg'],
                    'price_cents' => (int) $bracket['price'],
                    'sort' => $i * 10,
                ]);
            }
        });

        app(CarrierServiceRepository::class)->flushCache($carrier);
        app(CarrierGridRepository::class)->flushCache($carrier);

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
     * @return array<int, array{code: string, label: string, enabled: bool}>
     */
    public function getServices(): array
    {
        return app(CarrierServiceRepository::class)->allFor($this->carrierCode());
    }

    /**
     * @return array<int, array{max_kg: int, price: int, service_code: string|null}>
     */
    public function getGrid(): array
    {
        return app(CarrierGridRepository::class)->forCarrier($this->carrierCode());
    }

    /**
     * @return array<string, mixed>
     */
    public function getShipper(): array
    {
        return (array) config($this->carrierCode().'.shipper', []);
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
                }),
        ];
    }
}
