<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Database\Seeders\DatabaseSeeder;
use Filament\Actions\ActionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Tests\TestCase;

/**
 * En-tête de la fiche commande admin : actions regroupées dans un menu déroulant,
 * nom du chantier affiché dans le résumé plutôt qu'en faux bouton.
 */
class OrderAdminHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'order-header-test@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');
    }

    public function test_les_actions_sont_regroupees_dans_un_seul_menu(): void
    {
        $order = $this->makeOrder('Résidence Les Pins');

        $component = Livewire::test(ManageOrder::class, ['record' => $order->getKey()]);
        $headerActions = $component->instance()->getCachedHeaderActions();

        $this->assertCount(1, $headerActions);
        $this->assertInstanceOf(ActionGroup::class, $headerActions[0]);

        $names = array_keys($headerActions[0]->getFlatActions());
        $this->assertContains('update_status', $names);
        $this->assertContains('notify_order_delay', $names);
        $this->assertNotContains('site_name_badge', $names);

        // Les actions groupées restent appelables.
        $component->assertActionExists('notify_order_delay');
    }

    public function test_le_nom_du_chantier_est_affiche_dans_le_resume(): void
    {
        $order = $this->makeOrder('Résidence Les Pins');

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Résidence Les Pins')
            ->assertDontSee('Chantier : Résidence Les Pins');
    }

    public function test_pas_de_ligne_chantier_sans_nom(): void
    {
        $order = $this->makeOrder(null);

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertDontSee('Chantier');
    }

    private function makeOrder(?string $siteName): Order
    {
        $currency = Currency::query()->firstOrFail();

        return Order::factory()->create([
            'status' => 'payment-received',
            'placed_at' => now(),
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'channel_id' => Channel::query()->firstOrFail()->id,
            'pko_site_name' => $siteName,
        ])->refresh();
    }
}
