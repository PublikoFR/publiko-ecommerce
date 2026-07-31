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
 * Un client dont le profil porte déjà une adresse complète ne doit pas avoir à
 * revalider un formulaire pré-rempli à l'identique : le checkout s'ouvre sur le
 * récapitulatif, modifiable via le bouton « Modifier ».
 */
class CheckoutPrefilledAddressTest extends TestCase
{
    use RefreshDatabase;

    private function makeCart(bool $withCompleteProfile): Cart
    {
        $currency = Currency::factory()->create(['default' => true]);
        Country::factory()->create(['name' => 'Afghanistan', 'iso2' => 'AF', 'iso3' => 'AFG', 'native' => 'افغانستان']);
        Country::factory()->create(['name' => 'France', 'iso2' => 'FR', 'iso3' => 'FRA', 'native' => 'France']);

        $customer = Customer::factory()->create([
            'first_name' => 'Romain',
            'last_name' => 'GALVEZ',
            'company_name' => 'Weklo Test',
        ]);

        $customer->forceFill([
            'pko_street' => $withCompleteProfile ? '54 Rue des Châtaigniers' : null,
            'pko_city' => $withCompleteProfile ? 'Béziers' : null,
            'pko_postcode' => $withCompleteProfile ? '34500' : null,
            'meta' => ['phone' => '0785942940'],
        ])->save();

        // L'e-mail de contact du formulaire vient du compte connecté : sans
        // authentification, le pré-remplissage reste incomplet par construction.
        $user = User::factory()->create(['email' => 'riderfx3@gmail.com']);
        $this->actingAs($user);

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        CartSession::use($cart);

        return $cart;
    }

    public function test_un_profil_complet_ouvre_le_checkout_sur_le_recapitulatif(): void
    {
        $cart = $this->makeCart(withCompleteProfile: true);

        $component = Livewire::test(CheckoutPage::class);

        $steps = $component->get('steps');

        // L'adresse est posée sur le panier : l'étape livraison est franchie.
        $shipping = $cart->refresh()->shippingAddress;
        $this->assertNotNull($shipping, "L'adresse de livraison doit être enregistrée automatiquement");
        $this->assertSame('54 Rue des Châtaigniers', $shipping->line_one);
        $this->assertSame('Béziers', $shipping->city);
        $this->assertSame($this->franceId(), (int) $shipping->country_id);

        // shippingIsBilling est vrai par défaut → facturation servie d'office.
        $this->assertNotNull($cart->billingAddress, "L'adresse de facturation doit suivre");

        $component->assertNotSet('currentStep', $steps['shipping_address']);
    }

    public function test_un_profil_incomplet_laisse_le_formulaire_affiche(): void
    {
        $cart = $this->makeCart(withCompleteProfile: false);

        $component = Livewire::test(CheckoutPage::class);

        $this->assertNull($cart->refresh()->shippingAddress, 'Rien ne doit être enregistré tant que des champs manquent');
        $component->assertSet('currentStep', $component->get('steps')['shipping_address']);
    }

    public function test_le_client_peut_toujours_revenir_modifier_son_adresse(): void
    {
        $this->makeCart(withCompleteProfile: true);

        $component = Livewire::test(CheckoutPage::class);
        $steps = $component->get('steps');

        $component->set('currentStep', $steps['shipping_address'])
            ->assertSet('currentStep', $steps['shipping_address'])
            ->set('shipping.city', 'Montpellier')
            ->call('saveAddress', 'shipping')
            ->assertHasNoErrors();

        $this->assertSame('Montpellier', CartSession::current()->refresh()->shippingAddress->city);
    }

    private function franceId(): int
    {
        return (int) Country::query()->where('iso2', 'FR')->value('id');
    }
}
