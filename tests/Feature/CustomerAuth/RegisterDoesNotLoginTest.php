<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Pko\CustomerAuth\Livewire\RegisterPage;
use Pko\CustomerAuth\Sirene\SireneClient;
use Tests\TestCase;

/**
 * FEAT-006 : l'inscription ne connecte JAMAIS le nouveau client. Chaque compte pro
 * est validé manuellement par l'équipe ; après inscription le client est envoyé
 * sur la page « vérifiez votre boîte mail ». Cas verrouillé : INSEE désactivé
 * (configuration par défaut du projet).
 */
class RegisterDoesNotLoginTest extends TestCase
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

    public function test_registration_does_not_log_the_customer_in(): void
    {
        $this->assertGuest();

        Livewire::test(RegisterPage::class)
            ->set('siret', '981 043 979 00021')
            ->set('companyName', 'ACME SAS')
            ->set('firstName', 'Jean')
            ->set('lastName', 'Dupont')
            ->set('email', 'pro@example.test')
            ->set('emailConfirmation', 'pro@example.test')
            ->set('phone', '0600000000')
            ->set('street', '12 avenue des Roses')
            ->set('postcode', '69001')
            ->set('city', 'Lyon')
            ->set('password', 'secret123')
            ->set('passwordConfirmation', 'secret123')
            ->set('terms', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('register.check-inbox'));

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['email' => 'pro@example.test', 'email_verified_at' => null]);
        $this->assertSame('pro@example.test', session(RegisterPage::REGISTERED_EMAIL_SESSION_KEY));
        $this->assertNull(session('pko.just_registered'), 'Plus aucun flag d\'accès post-inscription.');
    }

    public function test_check_inbox_page_shows_the_registered_email(): void
    {
        $this->withSession([RegisterPage::REGISTERED_EMAIL_SESSION_KEY => 'pro@example.test'])
            ->get(route('register.check-inbox'))
            ->assertOk()
            ->assertSee('Vérifiez votre boîte mail')
            ->assertSee('pro@example.test');

        $this->assertGuest();
    }

    public function test_check_inbox_page_renders_without_session(): void
    {
        $this->get('/inscription/verifiez-vos-emails')
            ->assertOk()
            ->assertSee('Vérifiez votre boîte mail');
    }

    public function test_company_name_is_required(): void
    {
        Livewire::test(RegisterPage::class)
            ->set('siret', '98104397900021')
            ->set('companyName', null)
            ->set('firstName', 'Jean')
            ->set('lastName', 'Dupont')
            ->set('email', 'pro2@example.test')
            ->set('emailConfirmation', 'pro2@example.test')
            ->set('phone', '0600000000')
            ->set('password', 'secret123')
            ->set('passwordConfirmation', 'secret123')
            ->set('terms', true)
            ->call('submit')
            ->assertHasErrors(['companyName' => 'required']);

        $this->assertGuest();
    }

    public function test_mismatched_email_confirmation_is_rejected(): void
    {
        $this->fillValidForm('pro3@example.test')
            ->set('emailConfirmation', 'pro3@exemple.test')
            ->call('submit')
            ->assertHasErrors(['emailConfirmation'])
            ->assertSee('Les deux adresses e-mail ne correspondent pas.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'pro3@example.test']);
    }

    public function test_email_confirmation_ignores_case_and_surrounding_spaces(): void
    {
        $this->fillValidForm('pro4@example.test')
            ->set('emailConfirmation', '  PRO4@Example.test ')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('register.check-inbox'));

        $this->assertDatabaseHas('users', ['email' => 'pro4@example.test']);
        $this->assertGuest();
    }

    public function test_email_confirmation_is_required(): void
    {
        $this->fillValidForm('pro5@example.test')
            ->call('submit')
            ->assertHasErrors(['emailConfirmation' => 'required']);

        $this->assertDatabaseMissing('users', ['email' => 'pro5@example.test']);
    }

    private function fillValidForm(string $email): Testable
    {
        return Livewire::test(RegisterPage::class)
            ->set('siret', '98104397900021')
            ->set('companyName', 'ACME SAS')
            ->set('firstName', 'Jean')
            ->set('lastName', 'Dupont')
            ->set('email', $email)
            ->set('phone', '0600000000')
            ->set('password', 'secret123')
            ->set('passwordConfirmation', 'secret123')
            ->set('terms', true);
    }
}
