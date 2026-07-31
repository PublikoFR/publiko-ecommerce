<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CheckoutPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Tests\TestCase;

/**
 * Préremplissage de la première commande depuis la fiche client.
 *
 * Garde anti-régression : l'adresse saisie à l'inscription vit dans les
 * colonnes `pko_*`. `meta.sirene_address` n'est renseigné que si la
 * vérification INSEE est active (off par défaut) — s'appuyer uniquement
 * dessus laissait l'adresse et la raison sociale vides au checkout.
 */
class CheckoutPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_checkout_is_prefilled_from_the_customer_record(): void
    {
        $currency = Currency::factory()->create(['default' => true]);
        Country::factory()->create(['name' => 'France', 'iso3' => 'FRA']);

        $user = User::factory()->create(['email' => 'pro@example.test']);

        $customer = Customer::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'ACME SAS',
            'meta' => ['phone' => '0600000000'],
            'pko_street' => '12 avenue des Roses',
            'pko_postcode' => '69001',
            'pko_city' => 'Lyon',
            'pko_country' => 'FR',
        ]);
        $customer->users()->attach($user);

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
            'user_id' => $user->id,
            'customer_id' => $customer->id,
        ]);
        CartSession::use($cart);

        Livewire::actingAs($user)
            ->test(CheckoutPage::class)
            ->assertSet('shipping.company_name', 'ACME SAS')
            ->assertSet('shipping.first_name', 'Jean')
            ->assertSet('shipping.last_name', 'Dupont')
            ->assertSet('shipping.line_one', '12 avenue des Roses')
            ->assertSet('shipping.postcode', '69001')
            ->assertSet('shipping.city', 'Lyon')
            ->assertSet('shipping.contact_email', 'pro@example.test')
            ->assertSet('shipping.contact_phone', '0600000000')
            ->assertSet('billing.company_name', 'ACME SAS');
    }
}
