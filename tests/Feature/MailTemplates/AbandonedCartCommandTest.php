<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\ProductVariant;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\OrderNotifications\Mail\AbandonedCartMail;
use Tests\TestCase;

/**
 * Relance panier abandonné : délai configurable en back-office.
 */
class AbandonedCartCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_le_delai_est_lu_depuis_la_base(): void
    {
        $this->setDelayDays(3);

        // Panier abandonné il y a 4 jours → dans la fenêtre (3j < 4j < 17j).
        $cart = $this->makeAbandonedCart(daysAgo: 4);

        Mail::fake();

        $this->artisan('pko:mails:abandoned-carts')->assertSuccessful();

        Mail::assertQueued(AbandonedCartMail::class, fn ($m) => $m->hasTo($cart->user->email));
    }

    public function test_pas_d_envoi_avant_echeance(): void
    {
        $this->setDelayDays(5);

        // Panier abandonné il y a seulement 2 jours → trop récent.
        $this->makeAbandonedCart(daysAgo: 2);

        Mail::fake();

        $this->artisan('pko:mails:abandoned-carts')->assertSuccessful();

        Mail::assertNotQueued(AbandonedCartMail::class);
    }

    public function test_envoi_apres_echeance(): void
    {
        $this->setDelayDays(5);

        $cart = $this->makeAbandonedCart(daysAgo: 6);

        Mail::fake();

        $this->artisan('pko:mails:abandoned-carts')->assertSuccessful();

        Mail::assertQueued(AbandonedCartMail::class, fn ($m) => $m->hasTo($cart->user->email));
    }

    public function test_pas_de_double_envoi(): void
    {
        $this->setDelayDays(5);

        $cart = $this->makeAbandonedCart(daysAgo: 6);

        Mail::fake();

        $this->artisan('pko:mails:abandoned-carts')->assertSuccessful();
        $this->artisan('pko:mails:abandoned-carts')->assertSuccessful();

        // OnceMailer : le second run doit être bloqué par la contrainte unique.
        Mail::assertQueued(AbandonedCartMail::class, 1);
        Mail::assertQueued(AbandonedCartMail::class, fn ($m) => $m->hasTo($cart->user->email));
    }

    public function test_fallback_sur_config_si_aucune_valeur_en_base(): void
    {
        // Pas de settings.delay_days en base → fallback config (120 h = 5 j).
        config(['order-notifications.abandoned_cart_hours' => 72]); // 3 jours

        $cart = $this->makeAbandonedCart(daysAgo: 4);

        Mail::fake();

        $this->artisan('pko:mails:abandoned-carts')->assertSuccessful();

        Mail::assertQueued(AbandonedCartMail::class, fn ($m) => $m->hasTo($cart->user->email));
    }

    public function test_panier_converti_ignore(): void
    {
        $this->setDelayDays(5);

        $cart = $this->makeAbandonedCart(daysAgo: 6);
        // Marquer le panier comme complété (converti en commande).
        $cart->update(['completed_at' => now()->subDays(5)]);

        Mail::fake();

        $this->artisan('pko:mails:abandoned-carts')->assertSuccessful();

        Mail::assertNotQueued(AbandonedCartMail::class);
    }

    // -------------------------------------------------------------------------

    private function setDelayDays(int $days): void
    {
        $template = MailTemplate::where('key', 'cart.abandoned')->first();

        if ($template === null) {
            return;
        }

        $template->settings = ['delay_days' => $days];
        $template->saveQuietly(); // contourne ContentGuard (pas de changement de contenu)
    }

    /**
     * Crée un panier avec un utilisateur lié, abandonné il y a $daysAgo jours.
     */
    private function makeAbandonedCart(int $daysAgo): Cart
    {
        $user = User::factory()->create();
        $channel = Channel::getDefault();
        $currency = Currency::getDefault();

        $cart = Cart::create([
            'channel_id' => $channel->id,
            'currency_id' => $currency->id,
            'user_id' => $user->id,
        ]);

        $variant = ProductVariant::first();

        CartLine::create([
            'cart_id' => $cart->id,
            'purchasable_type' => ProductVariant::class,
            'purchasable_id' => $variant->id,
            'quantity' => 1,
        ]);

        // Simuler l'abandon il y a $daysAgo jours.
        $cart->update(['updated_at' => now()->subDays($daysAgo)]);

        return $cart->fresh();
    }
}
