<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Pko\CustomerAuth\Support\JustRegistered;
use Pko\CustomerAuth\Support\ProAccess;
use Tests\TestCase;

class ProAccessRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function makeUser(string $email, string $sireneStatus): User
    {
        $user = User::create([
            'name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create([
            'first_name' => '',
            'last_name' => '',
            'company_name' => 'Test SARL',
            'sirene_status' => $sireneStatus,
        ]);

        // Groupe requis pour l'accès pro (config default_customer_group_handle).
        $handle = (string) config('customer-auth.default_customer_group_handle', 'nouveau-client');
        $group = CustomerGroup::firstOrCreate(['handle' => $handle], ['name' => 'Nouveau client']);
        $customer->customerGroups()->attach($group);
        $customer->users()->attach($user);

        return $user;
    }

    public function test_pending_user_does_not_loop_between_login_and_account(): void
    {
        $user = $this->makeUser('pending@example.test', 'pending');
        $this->actingAs($user);

        // /connexion NE DOIT PAS rediriger vers /compte (sinon boucle) → page rendue.
        $this->get('/connexion')->assertOk();

        // /compte renvoie vers /connexion (un seul saut, pas une boucle).
        $this->get('/compte')->assertRedirectContains('/connexion');
    }

    public function test_active_pro_user_is_redirected_from_login_to_account(): void
    {
        $user = $this->makeUser('active@example.test', 'active');
        $this->actingAs($user);

        $this->get('/connexion')->assertRedirect('/compte');
    }

    public function test_freshly_registered_pending_user_keeps_full_access(): void
    {
        // Régression « demi-connexion » : après inscription, l'utilisateur est
        // auto-connecté alors que son compte est encore `pending` (e-mail non
        // vérifié). Le flag JustRegistered doit lui accorder un accès COMPLET le
        // temps de sa session — pas seulement afficher son nom pendant que toutes
        // les routes pro rebondissent vers /connexion.
        $user = $this->makeUser('fresh@example.test', 'active');
        $user->customers()->first()->update(['pko_status' => 'pending']);
        $user = $user->fresh();

        // Sans le flag : compte pending → accès refusé (comportement de durcissement).
        $this->assertNotNull(ProAccess::denialReason($user));

        // Avec le flag (posé par RegisterPage juste après l'auto-login) : accès accordé.
        JustRegistered::flag();
        $this->assertNull(ProAccess::denialReason($user));

        // Et de bout en bout : /compte ne rebondit pas, l'utilisateur reste connecté.
        $this->actingAs($user)
            ->withSession(['pko.just_registered' => true])
            ->get('/compte')
            ->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
