<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Lunar\Admin\Models\Staff;
use Pko\CustomerAuth\Support\ProAccess;
use Tests\TestCase;

/**
 * Déconnexion front — route closure simple (logout + invalidate) dans
 * `routes/web.php`. Le lien est un `<form method="POST">` plein-page : la
 * navigation dure abandonne côté navigateur toute requête Livewire en vol, il
 * n'y a donc pas de « session zombie » à neutraliser hors-bande.
 */
class LogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_logout_logs_the_user_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/deconnexion')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    /**
     * Idempotente : rejouée sur une session déjà déconnectée (double clic, renvoi
     * de formulaire), elle redirige sans erreur.
     */
    public function test_logout_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/deconnexion')->assertRedirect('/');
        $this->post('/deconnexion')->assertRedirect('/');

        $this->assertGuest();
    }

    /**
     * Le flag « fraîchement inscrit » (accès complet malgré `pending`) ne doit pas
     * survivre à la déconnexion, sans quoi un compte pending qui se reconnecte
     * dans la même session rouvrirait l'accès.
     */
    public function test_logout_clears_the_just_registered_flag(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['pko.just_registered' => true])
            ->post('/deconnexion')
            ->assertRedirect('/')
            ->assertSessionMissing('pko.just_registered');
    }

    /**
     * Impersonation admin : le staff partage le cookie de session. La déconnexion
     * FRONT ne doit vider que la partie client (guard web + marqueur
     * d'impersonation), sans `invalidate()` qui éjecterait l'admin de son panel.
     */
    public function test_front_logout_preserves_staff_impersonation(): void
    {
        $staff = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'admin' => true,
        ]);
        $user = User::factory()->create();

        $this->be($staff, 'staff')
            ->be($user, 'web')
            ->withSession([ProAccess::IMPERSONATOR_SESSION_KEY => $staff->getKey()])
            ->post('/deconnexion')
            ->assertRedirect('/')
            ->assertSessionMissing(ProAccess::IMPERSONATOR_SESSION_KEY);

        $this->assertGuest('web');
        $this->assertAuthenticatedAs($staff, 'staff');
    }
}
