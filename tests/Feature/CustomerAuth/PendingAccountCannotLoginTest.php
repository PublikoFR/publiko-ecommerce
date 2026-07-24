<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Pko\CustomerAuth\Livewire\LoginPage;
use Pko\CustomerAuth\Mail\EmailVerificationMail;
use Tests\TestCase;

/**
 * Règle produit : un compte `pending` ne doit JAMAIS rester authentifié.
 *
 * Avant ce correctif, `LoginPage::authenticate()` appelait `Auth::attempt()`
 * puis se contentait de rediriger : l'utilisateur restait connecté sans accès à
 * la moindre page — son nom s'affichait sous le picto profil (« semi-connexion »).
 * La connexion doit être refusée, et le lien de vérification renvoyé.
 */
class PendingAccountCannotLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_a_pending_account_is_refused_and_never_left_authenticated(): void
    {
        Mail::fake();
        $this->pendingUser();

        $tokenAvant = session()->token();

        Livewire::test(LoginPage::class)
            ->set('email', 'pending@example.test')
            ->set('password', 'secret-password')
            ->call('authenticate')
            ->assertHasErrors('email')
            ->assertNoRedirect();

        $this->assertGuest('web');

        // Le refus ne doit PAS périmer le token CSRF : la page de connexion reste
        // affichée et doit rester utilisable. Sinon la tentative suivante part en
        // 419 « This page has expired ».
        $this->assertSame(
            $tokenAvant,
            session()->token(),
            'Le refus de connexion ne doit pas invalider le token CSRF de la page.'
        );
        Mail::assertSent(EmailVerificationMail::class);
    }

    public function test_an_active_account_still_logs_in(): void
    {
        $user = $this->pendingUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $customer = $user->customers()->first();
        $customer->setAttribute('pko_status', 'active');
        $customer->save();

        Livewire::test(LoginPage::class)
            ->set('email', 'pending@example.test')
            ->set('password', 'secret-password')
            ->call('authenticate')
            ->assertRedirect('/compte');

        $this->assertAuthenticatedAs($user);
    }

    private function pendingUser(): User
    {
        $user = User::create([
            'name' => 'Tortank Pikachu',
            'email' => 'pending@example.test',
            'password' => Hash::make('secret-password'),
            'email_verified_at' => null,
        ]);

        $customer = Customer::create([
            'first_name' => 'Tortank',
            'last_name' => 'Pikachu',
            'pko_status' => 'pending',
            'sirene_status' => 'active',
        ]);
        $customer->users()->attach($user);

        $handle = (string) config('customer-auth.default_customer_group_handle', 'nouveau-client');
        $group = CustomerGroup::firstOrCreate(['handle' => $handle], ['name' => 'Nouveau client']);
        $customer->customerGroups()->attach($group);

        return $user;
    }
}
