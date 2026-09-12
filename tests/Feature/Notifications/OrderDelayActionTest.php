<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Pko\OrderNotifications\Mail\OrderDelayedMail;
use Tests\TestCase;

/**
 * Action « Signaler un retard » (E-mail 09) sur la fiche commande.
 *
 * Bug corrigé : resolveOrder() utilisait request()->route('record'), absent des
 * requêtes Livewire (POST /livewire/update) → $order = null → bouton masqué et
 * aucun mail envoyé. Correctif : $this->caller?->record.
 */
class OrderDelayActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'order-delay-test@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');
    }

    public function test_le_mail_retard_est_mis_en_queue_apres_laction(): void
    {
        Mail::fake();

        $order = $this->makeOrder('awaiting-payment');

        Livewire::test(ManageOrder::class, ['record' => $order->getKey()])
            ->callAction('notify_order_delay', data: [
                'new_date' => now()->addDays(7)->format('Y-m-d'),
            ]);

        Mail::assertQueued(OrderDelayedMail::class, function (OrderDelayedMail $mail) use ($order): bool {
            return $mail->order->is($order)
                && $mail->hasTo('client@example.test');
        });
    }

    public function test_deux_signalements_successifs_envoient_deux_mails(): void
    {
        Mail::fake();

        $order = $this->makeOrder('dispatched');

        $component = Livewire::test(ManageOrder::class, ['record' => $order->getKey()]);

        $component->callAction('notify_order_delay', data: [
            'new_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $component->callAction('notify_order_delay', data: [
            'new_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        Mail::assertQueued(OrderDelayedMail::class, 2);
    }

    public function test_le_bouton_est_visible_pour_une_commande_active(): void
    {
        $order = $this->makeOrder('dispatched');

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Signaler un retard');
    }

    public function test_le_bouton_est_masque_pour_une_commande_livree(): void
    {
        $order = $this->makeOrder('delivered');

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertDontSee('Signaler un retard');
    }

    public function test_le_bouton_est_masque_pour_une_commande_annulee(): void
    {
        $order = $this->makeOrder('cancelled');

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertDontSee('Signaler un retard');
    }

    private function makeOrder(string $status): Order
    {
        $currency = Currency::query()->firstOrFail();
        $channel = Channel::query()->firstOrFail();

        $order = Order::factory()->create([
            'status' => $status,
            'placed_at' => now(),
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'channel_id' => $channel->id,
        ]);

        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'contact_email' => 'client@example.test',
            'first_name' => 'Camille',
            'country_id' => Country::query()->firstOrFail()->id,
        ]);

        return $order->refresh();
    }
}
