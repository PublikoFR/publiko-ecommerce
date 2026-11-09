<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
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
     * Un compte est actif ⟺ l'équipe l'a validé (`pko_status = active`). Le SIRET
     * (valeur non fiable : non vérifiée quand INSEE est off, voire null) ne gate
     * pas l'accès. On modélise donc l'état via `pko_status`, jamais via `sirene_status`.
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
        // Un compte validé (pko_status='active') avec un SIRET 'pending' (INSEE off)
        // accède normalement — le SIRET ne gate pas l'accès.
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

    public function test_verified_pending_user_awaits_manual_validation(): void
    {
        // FEAT-006 : un e-mail confirmé n'ouvre pas l'accès, seul `active` (posé
        // par l'équipe) le fait.
        $user = $this->makeUser('waiting@example.test', 'pending');
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->assertSame(ProAccess::AWAITING_VALIDATION_REASON, ProAccess::denialReason($user->fresh()));
    }

    public function test_unverified_pending_user_is_asked_to_confirm_email(): void
    {
        $user = $this->makeUser('unverified@example.test', 'pending');

        $this->assertSame(ProAccess::UNVERIFIED_EMAIL_REASON, ProAccess::denialReason($user));
    }

    public function test_active_account_with_unverified_email_has_access(): void
    {
        // Compte créé actif en back-office (l'équipe l'a ouvert elle-même) : le
        // critère d'accès est le statut, pas la vérification de l'e-mail.
        $user = $this->makeUser('backoffice@example.test', 'active');
        $user->forceFill(['email_verified_at' => null])->save();

        $this->assertNull(ProAccess::denialReason($user->fresh()));
    }

    public function test_stale_just_registered_session_key_grants_nothing(): void
    {
        $user = $this->makeUser('fresh@example.test', 'pending');

        $this->actingAs($user)
            ->withSession(['pko.just_registered' => true])
            ->get('/compte')
            ->assertRedirectContains('/connexion');

        $this->assertGuest('web');
    }
}
