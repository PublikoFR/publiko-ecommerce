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

    /**
     * Un compte est actif ⟺ son e-mail est vérifié. Le SIRET (valeur non fiable :
     * non vérifiée quand INSEE est off, voire null) ne gate pas l'accès. On modélise
     * donc l'état via `pko_status` + `email_verified_at`, jamais via `sirene_status`.
     */
    private function makeUser(string $email, string $pkoStatus): User
    {
        $user = User::create([
            'name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => $pkoStatus === 'active' ? now() : null,
        ]);

        $customer = Customer::create([
            'first_name' => '',
            'last_name' => '',
            'company_name' => 'Test SARL',
            'pko_status' => $pkoStatus,
            // SIRET volontairement 'pending' (INSEE indisponible) : ne doit rien gater.
            'sirene_status' => 'pending',
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

    public function test_verified_account_is_active_even_when_siret_is_pending(): void
    {
        // Cœur de la correction : un compte e-mail-vérifié (pko_status='active')
        // avec un SIRET 'pending' (INSEE off) accède normalement — le SIRET ne gate plus.
        $user = $this->makeUser('verified@example.test', 'active');

        $this->assertNull(ProAccess::denialReason($user));
    }

    public function test_customer_keeps_access_after_default_group_is_removed(): void
    {
        // Régression signalée par le client : retirer le groupe « Nouveau client »
        // depuis la fiche client (geste normal de qualification en back-office)
        // rendait le compte inconnectable — « Accès réservé aux comptes
        // professionnels » — jusqu'à ce qu'on lui remette le groupe.
        $user = $this->makeUser('degrouped@example.test', 'active');
        $customer = $user->customers()->first();

        $customer->customerGroups()->detach();

        $this->assertNull(ProAccess::denialReason($user->fresh()));

        $this->actingAs($user)->get('/compte')->assertOk();
    }

    public function test_customer_in_another_group_only_keeps_access(): void
    {
        // Variante du même geste : l'admin remplace « Nouveau client » par le
        // groupe métier du client. L'accès doit suivre.
        $user = $this->makeUser('metier@example.test', 'active');
        $customer = $user->customers()->first();

        $metier = CustomerGroup::firstOrCreate(
            ['handle' => 'installateurs'],
            ['name' => 'Installateurs', 'pko_is_metier' => true],
        );
        $customer->customerGroups()->sync([$metier->id]);

        $this->assertNull(ProAccess::denialReason($user->fresh()));
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
