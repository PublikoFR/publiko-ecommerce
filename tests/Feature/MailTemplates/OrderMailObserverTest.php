<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Country;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\OrderNotifications\Mail\FirstOrderGiftMail;
use Pko\OrderNotifications\Mail\OrderConfirmedMail;
use Pko\OrderNotifications\Mail\OrderDeliveredMail;
use Pko\OrderNotifications\Mail\OrderPaymentReceivedMail;
use Pko\OrderNotifications\Mail\OrderPlacedAdminMail;
use Pko\OrderNotifications\Mail\PaymentOfflineAdminMail;
use Pko\OrderNotifications\Support\OrderMailData;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;

class OrderMailObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_la_confirmation_part_quand_la_commande_est_passee(): void
    {
        Mail::fake();

        $order = $this->makeOrder();
        $order->update(['placed_at' => now()]);

        Mail::assertQueued(OrderConfirmedMail::class);
        Mail::assertNotQueued(OrderPlacedAdminMail::class);
    }

    public function test_la_notification_equipe_part_quand_la_commande_est_passee(): void
    {
        Mail::fake();
        Setting::set('admin_email', 'ops@example.test');

        $order = $this->makeOrder();
        $order->update(['placed_at' => now()]);

        Mail::assertQueued(OrderPlacedAdminMail::class, function (OrderPlacedAdminMail $mail) use ($order): bool {
            return $mail->hasTo('ops@example.test')
                && $mail->values['order_reference'] === (string) $order->fresh()->reference;
        });
    }

    public function test_le_virement_declenche_la_notification_equipe(): void
    {
        Mail::fake();
        Setting::set('admin_email', 'ops@example.test');

        $order = $this->makeOrder(['placed_at' => now()]);
        $order->update(['status' => 'payment-offline']);

        Mail::assertQueued(PaymentOfflineAdminMail::class, function (PaymentOfflineAdminMail $mail): bool {
            return $mail->hasTo('ops@example.test');
        });
    }

    public function test_le_paiement_recu_part_au_passage_en_statut_paye(): void
    {
        Mail::fake();

        $order = $this->makeOrder(['placed_at' => now()]);
        $order->update(['status' => 'payment-received']);

        Mail::assertQueued(OrderPaymentReceivedMail::class);
    }

    /**
     * Cœur de la garde d'idempotence : un observer de statut se rejoue
     * (reprise de webhook, backfill), le client ne doit pas être notifié deux fois.
     */
    public function test_le_paiement_recu_ne_part_qu_une_fois(): void
    {
        Mail::fake();

        $order = $this->makeOrder(['placed_at' => now()]);
        $order->update(['status' => 'payment-received']);
        $order->update(['status' => 'dispatched']);
        $order->update(['status' => 'payment-received']);

        Mail::assertQueued(OrderPaymentReceivedMail::class, 1);
    }

    public function test_la_livraison_declenche_le_mail_correspondant(): void
    {
        Mail::fake();

        $order = $this->makeOrder(['placed_at' => now()]);
        $order->update(['status' => 'delivered']);

        Mail::assertQueued(OrderDeliveredMail::class);
    }

    public function test_le_mail_premiere_commande_ne_part_que_pour_la_premiere(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create();

        $first = $this->makeOrder(['placed_at' => now(), 'customer_id' => $customer->id]);
        $first->update(['status' => 'payment-received']);

        Mail::assertQueued(FirstOrderGiftMail::class, 1);

        $second = $this->makeOrder(['placed_at' => now(), 'customer_id' => $customer->id]);
        $second->update(['status' => 'payment-received']);

        // Toujours un seul : la deuxième commande n'est pas une première.
        Mail::assertQueued(FirstOrderGiftMail::class, 1);
    }

    public function test_un_modele_desactive_bloque_l_envoi(): void
    {
        Mail::fake();

        // Le modèle est seedé par le DatabaseSeeder : on le désactive.
        MailTemplate::query()
            ->where('key', 'order.confirmed')
            ->firstOrFail()
            ->update(['enabled' => false]);

        $order = $this->makeOrder();
        $order->update(['placed_at' => now()]);

        Mail::assertNotQueued(OrderConfirmedMail::class);
    }

    /** Le texte client annonce « € HT » : c'est sub_total qui doit être affiché. */
    public function test_le_montant_affiche_est_le_montant_hors_taxes(): void
    {
        $order = $this->makeOrder(['sub_total' => 124_890, 'total' => 149_868]);

        $this->assertSame('1 248,90', OrderMailData::totalExcludingTax($order->refresh()));
    }

    /**
     * Commande dotée d'une adresse de livraison porteuse d'un e-mail : sans
     * destinataire, l'observer s'abstient et le test ne prouverait rien.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeOrder(array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'status' => 'awaiting-payment',
            'sub_total' => 100_00,
            'placed_at' => null,
        ], $attributes));

        // `country_id` est requis : l'observer de lunarphp/table-rate-shipping
        // construit un PostcodeLookup depuis le pays de l'adresse et lève une
        // TypeError s'il est nul.
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
