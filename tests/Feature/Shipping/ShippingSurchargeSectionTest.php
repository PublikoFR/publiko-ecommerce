<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Pko\ShippingCommon\Filament\Pages\ShippingSettingsPage;
use Pko\ShippingCommon\Models\ShippingSurcharge;
use Tests\TestCase;

/**
 * Suppléments transport : section de la page Paramètres d'expédition
 * (plus de Resource dédiée).
 */
class ShippingSurchargeSectionTest extends TestCase
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

    public function test_la_page_parametres_liste_les_supplements(): void
    {
        $corse = ShippingSurcharge::query()->where('code', 'corse')->firstOrFail();

        Livewire::test(ShippingSettingsPage::class)
            ->assertSuccessful()
            ->assertSee('Suppléments transport')
            ->assertCanSeeTableRecords([$corse]);
    }

    public function test_creation_en_modale_avec_montant_saisi_en_euros(): void
    {
        Livewire::test(ShippingSettingsPage::class)
            ->callTableAction('create', data: [
                'code' => 'hayon',
                'label' => 'Livraison avec hayon',
                'mode' => 'auto',
                'amount_cents' => '15.50',
                'enabled' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('pko_shipping_surcharges', [
            'code' => 'hayon',
            'amount_cents' => 1550,
            'mode' => 'auto',
        ]);
    }

    public function test_edition_conserve_le_montant_en_cents(): void
    {
        $surcharge = ShippingSurcharge::query()->where('code', 'corse')->firstOrFail();
        $surcharge->update(['amount_cents' => 2500]);

        Livewire::test(ShippingSettingsPage::class)
            ->mountTableAction('edit', $surcharge)
            ->assertTableActionDataSet(['amount_cents' => '25.00'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(2500, $surcharge->fresh()->amount_cents);
    }

    public function test_surcharges_seeded(): void
    {
        $this->assertDatabaseHas('pko_shipping_surcharges', [
            'code' => 'corse',
            'enabled' => true,
        ]);

        $this->assertDatabaseHas('pko_shipping_surcharges', [
            'code' => 'transport_specifique',
            'mode' => 'quote',
        ]);

        $this->assertSame(9, ShippingSurcharge::query()->count());
    }
}
