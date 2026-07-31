<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Pko\CustomerAuth\Livewire\RegisterPage;
use Pko\CustomerAuth\Sirene\SireneClient;
use Tests\TestCase;

/**
 * Garde anti-régression : l'inscription connecte TOUJOURS le nouveau client.
 *
 * L'auto-login a été régressé plusieurs fois en le reconditionnant au statut
 * SIRENE. Or `Status::Pending` est le statut nominal dès que l'INSEE n'est pas
 * consulté (INSEE_ENABLED=false, le défaut) : gater dessus revient à ne jamais
 * auto-connecter personne. Ce test verrouille le cas « INSEE désactivé ».
 */
class RegisterAutoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        Mail::fake();
        Http::preventStrayRequests();

        // INSEE désactivé = configuration par défaut du projet.
        $this->app->singleton(SireneClient::class, fn () => new SireneClient(
            baseUrl: 'https://api.insee.fr/api-sirene/3.11',
            apiKey: '',
            enabled: false,
        ));
    }

    public function test_registration_logs_the_customer_in_when_insee_is_disabled(): void
    {
        $this->assertGuest();

        Livewire::test(RegisterPage::class)
            ->set('siret', '981 043 979 00021')
            ->set('companyName', 'ACME SAS')
            ->set('firstName', 'Jean')
            ->set('lastName', 'Dupont')
            ->set('email', 'pro@example.test')
            ->set('phone', '0600000000')
            ->set('street', '12 avenue des Roses')
            ->set('postcode', '69001')
            ->set('city', 'Lyon')
            ->set('password', 'secret123')
            ->set('passwordConfirmation', 'secret123')
            ->set('terms', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertSame('pro@example.test', auth()->user()->email);
    }

    public function test_company_name_is_required(): void
    {
        Livewire::test(RegisterPage::class)
            ->set('siret', '98104397900021')
            ->set('companyName', null)
            ->set('firstName', 'Jean')
            ->set('lastName', 'Dupont')
            ->set('email', 'pro2@example.test')
            ->set('phone', '0600000000')
            ->set('password', 'secret123')
            ->set('passwordConfirmation', 'secret123')
            ->set('terms', true)
            ->call('submit')
            ->assertHasErrors(['companyName' => 'required']);

        $this->assertGuest();
    }
}
