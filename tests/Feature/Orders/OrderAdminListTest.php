<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ListOrders;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Tests\TestCase;

/**
 * Liste des commandes admin : colonnes Date · Référence · Client · Statut · Total.
 */
class OrderAdminListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->actingAs(Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'order-list-test@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]), 'staff');
    }

    private function makeOrder(array $attributes = []): Order
    {
        $currency = Currency::query()->firstOrFail();

        $customer = Customer::factory()->create(['company_name' => 'Durand Bâtiment']);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'payment-received',
            'reference' => 'LIST-0001',
            'new_customer' => true,
            'placed_at' => Carbon::parse('2026-09-01 16:32', config('app.timezone')),
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'channel_id' => Channel::query()->firstOrFail()->id,
            ...$attributes,
        ]);

        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'type' => 'billing',
            // Saisie libre sur l'adresse : ne doit jamais être affichée ni recherchée.
            'company_name' => 'Adresse Libre SARL',
            'first_name' => 'Jeanne',
            'last_name' => 'Durand',
            'contact_email' => 'jeanne.durand@example.com',
            'contact_phone' => '0612345678',
            'country_id' => Country::query()->firstOrFail()->id,
        ]);

        return $order->refresh();
    }

    public function test_les_colonnes_suivent_l_ordre_attendu(): void
    {
        $component = Livewire::test(ListOrders::class);

        $columns = array_keys($component->instance()->getTable()->getColumns());

        $this->assertSame(['placed_at', 'reference', 'billingAddress.fullName', 'status', 'total'], $columns);
    }

    public function test_une_ligne_affiche_date_fr_client_sur_trois_lignes_et_deux_badges(): void
    {
        $order = $this->makeOrder();

        $component = Livewire::test(ListOrders::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$order])
            ->assertSee('01/09/26 - 16h32')
            ->assertSee('LIST-0001')
            ->assertSee('Nouveau');

        // Sur le HTML rendu : la réponse Livewire complète encode les accents en JSON.
        $html = $component->html();
        $positions = array_map(
            fn (string $needle): int|false => mb_strpos($html, $needle),
            ['Durand Bâtiment', 'Jeanne Durand', 'jeanne.durand@example.com', '0612345678'],
        );
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Raison sociale, nom, e-mail puis téléphone.');
        $this->assertStringNotContainsString('Adresse Libre SARL', $html);
    }

    public function test_la_recherche_trouve_une_commande_par_email_telephone_ou_raison_sociale(): void
    {
        $order = $this->makeOrder();
        $other = $this->makeOrder(['reference' => 'LIST-0002']);
        $other->customer->update(['company_name' => 'Autre SARL']);
        $other->billingAddress->update(['contact_email' => 'autre@example.com', 'contact_phone' => '0700000000']);

        Livewire::test(ListOrders::class)
            ->loadTable()
            ->searchTable('jeanne.durand@')
            ->assertCanSeeTableRecords([$order])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('0700000000')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$order])
            ->searchTable('Durand Bât')
            ->assertCanSeeTableRecords([$order])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('Adresse Libre')
            ->assertCanNotSeeTableRecords([$order, $other]);
    }

    public function test_une_adresse_sans_nom_affiche_quand_meme_la_raison_sociale(): void
    {
        $order = $this->makeOrder();
        $order->billingAddress->update(['first_name' => '', 'last_name' => '']);

        $html = Livewire::test(ListOrders::class)->loadTable()->html();

        $this->assertStringContainsString('Durand Bâtiment', $html);
        $this->assertStringContainsString('jeanne.durand@example.com', $html);
    }
}
