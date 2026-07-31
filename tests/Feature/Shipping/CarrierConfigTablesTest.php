<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Pko\ShippingChronopost\Filament\Pages\ChronopostConfig;
use Pko\ShippingCommon\Filament\Livewire\CarrierGridTable;
use Pko\ShippingCommon\Filament\Livewire\CarrierServicesTable;
use Pko\ShippingCommon\Filament\Pages\ShippingSettingsPage;
use Pko\ShippingCommon\Models\CarrierGridBracket;
use Pko\ShippingCommon\Models\CarrierService;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Tests\TestCase;

class CarrierConfigTablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        /** @var User $admin */
        $admin = User::query()->first();
        $this->assertNotNull($admin, 'Un utilisateur admin seedé est requis.');
        $this->actingAs($admin);
    }

    public function test_carrier_config_page_renders(): void
    {
        Livewire::test(ChronopostConfig::class)->assertSuccessful();
    }

    public function test_services_table_lists_carrier_services(): void
    {
        $services = CarrierService::query()->where('carrier_code', 'chronopost')->get();
        $this->assertNotEmpty($services, 'Le seed doit fournir des services Chronopost.');

        Livewire::test(CarrierServicesTable::class, ['carrierCode' => 'chronopost'])
            ->assertSuccessful()
            ->assertCanSeeTableRecords($services);
    }

    public function test_services_table_search_stays_scoped_to_the_carrier(): void
    {
        $colissimo = CarrierService::query()->where('carrier_code', 'colissimo')->firstOrFail();
        $colissimo->update(['label' => 'Livraison standard — Colissimo']);

        $chronopost = CarrierService::query()
            ->where('carrier_code', 'chronopost')
            ->where('label', 'like', '%standard%')
            ->firstOrFail();

        Livewire::test(CarrierServicesTable::class, ['carrierCode' => 'chronopost'])
            ->searchTable('standard')
            ->assertCanSeeTableRecords([$chronopost])
            ->assertCanNotSeeTableRecords([$colissimo]);
    }

    public function test_services_table_creates_and_deletes_a_service(): void
    {
        $component = Livewire::test(CarrierServicesTable::class, ['carrierCode' => 'chronopost']);

        $component->callTableAction('create', data: [
            'service_code' => 'chrono_test',
            'label' => 'Chrono Test',
            'enabled' => true,
        ])->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('pko_carrier_services', [
            'carrier_code' => 'chronopost',
            'service_code' => 'chrono_test',
            'enabled' => true,
        ]);

        // Le cache repository est invalidé par l'écriture modèle.
        $codes = array_column(app(CarrierServiceRepository::class)->allFor('chronopost'), 'code');
        $this->assertContains('chrono_test', $codes);

        $record = CarrierService::query()->where('service_code', 'chrono_test')->firstOrFail();

        $component->callTableAction('delete', $record)->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('pko_carrier_services', ['id' => $record->id]);
        $this->assertNotContains(
            'chrono_test',
            array_column(app(CarrierServiceRepository::class)->allFor('chronopost'), 'code'),
        );
    }

    public function test_services_table_rejects_duplicate_code_for_same_carrier(): void
    {
        $existing = CarrierService::query()->where('carrier_code', 'chronopost')->firstOrFail();

        Livewire::test(CarrierServicesTable::class, ['carrierCode' => 'chronopost'])
            ->callTableAction('create', data: [
                'service_code' => $existing->service_code,
                'label' => 'Doublon',
                'enabled' => true,
            ])
            ->assertHasTableActionErrors(['service_code']);
    }

    public function test_grid_table_creates_a_bracket_in_cents_from_euros(): void
    {
        Livewire::test(CarrierGridTable::class, ['carrierCode' => 'chronopost'])
            ->assertSuccessful()
            ->callTableAction('create', data: [
                'service_code' => null,
                'max_kg' => 42,
                'price_eur' => 12.90,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('pko_carrier_grids', [
            'carrier_code' => 'chronopost',
            'service_code' => null,
            'max_kg' => 42,
            'price_cents' => 1290,
        ]);
    }

    public function test_grid_table_edits_a_bracket(): void
    {
        $bracket = CarrierGridBracket::query()->where('carrier_code', 'chronopost')->firstOrFail();

        Livewire::test(CarrierGridTable::class, ['carrierCode' => 'chronopost'])
            ->callTableAction('edit', $bracket, data: [
                'service_code' => $bracket->service_code,
                'max_kg' => $bracket->max_kg,
                'price_eur' => 99.5,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(9950, $bracket->fresh()->price_cents);
    }

    public function test_shipping_settings_page_offers_service_options(): void
    {
        $component = Livewire::test(ShippingSettingsPage::class)->assertSuccessful();

        $options = $component->instance()->form->getComponent('data.services')->getOptions();

        $codes = array_merge(...array_values(array_map('array_keys', $options)));
        $this->assertNotEmpty($codes, 'Le select doit proposer les services actifs déclarés en base.');

        $enabled = CarrierService::query()->where('enabled', true)->firstOrFail();
        $this->assertContains((string) $enabled->service_code, $codes);

        // Un service désactivé (transporteur en veille) ne doit pas être proposé.
        $disabled = CarrierService::query()->where('enabled', false)->first();
        $this->assertNotNull($disabled, 'Le seed doit contenir au moins un service désactivé.');
        $this->assertNotContains((string) $disabled->service_code, $codes);
    }
}
