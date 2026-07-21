<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Actions\ImpersonateCustomerUser;
use Pko\CustomerAuth\Support\ProAccess;
use Tests\TestCase;

/**
 * Régression : l'impersonation depuis le back-office plantait avec
 * « Call to undefined method Lunar\Admin\Models\Staff::carts() ».
 *
 * Cause : pendant une requête Filament le guard par défaut est `staff` ; le
 * listener Lunar branché sur l'événement Login (CartSessionAuthListener) résout
 * l'utilisateur via le guard **par défaut** et tombait donc sur le Staff.
 */
class ImpersonateCustomerUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_impersonation_logs_in_the_web_user_while_the_staff_guard_is_active(): void
    {
        $staff = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'admin' => true,
        ]);

        $user = User::create([
            'name' => 'Jean Dupont',
            'email' => 'jean@example.test',
            'password' => Hash::make('password'),
        ]);

        // On reproduit le contexte d'une requête Filament : staff connecté ET
        // guard par défaut basculé sur `staff` par le panel Lunar.
        Auth::guard('staff')->login($staff);
        Auth::shouldUse('staff');

        app(ImpersonateCustomerUser::class)($user);

        $this->assertSame($user->id, Auth::guard('web')->id(), 'Le client doit être connecté sur le guard web.');
        $this->assertSame($staff->id, Auth::guard('staff')->id(), 'La session admin doit rester intacte.');
        $this->assertSame('staff', Auth::getDefaultDriver(), 'Le guard par défaut doit être restauré après le login.');
        $this->assertSame($staff->id, session(ProAccess::IMPERSONATOR_SESSION_KEY), 'Le staff à l\'origine doit être tracé en session.');
    }

    public function test_impersonation_bypasses_the_pro_gate_on_a_pending_unverified_account(): void
    {
        [$staff, $user] = $this->pendingCustomerAndStaff();

        // Sans impersonation, le gate refuse : compte pending + e-mail non vérifié.
        $this->assertNotNull(ProAccess::denialReason($user));

        Auth::guard('staff')->login($staff);
        Auth::shouldUse('staff');
        app(ImpersonateCustomerUser::class)($user);
        Auth::shouldUse('web');

        $this->assertNull(
            ProAccess::denialReason($user),
            'Une session ouverte par un admin doit traverser le gate pro.'
        );
    }

    public function test_pro_gate_still_blocks_a_pending_account_without_impersonation(): void
    {
        [, $user] = $this->pendingCustomerAndStaff();

        $this->actingAs($user)
            ->get('/compte')
            ->assertRedirectContains('/connexion');
    }

    public function test_account_dashboard_is_reachable_while_impersonating(): void
    {
        [$staff, $user] = $this->pendingCustomerAndStaff();

        // Session telle qu'elle existe après l'impersonation : staff authentifié
        // (clé de session du SessionGuard) + flag posé par l'action.
        $this->withSession([
            'login_staff_'.sha1(SessionGuard::class) => $staff->getAuthIdentifier(),
            ProAccess::IMPERSONATOR_SESSION_KEY => $staff->getKey(),
        ])
            ->actingAs($user)
            ->get('/compte')
            ->assertOk();
    }

    public function test_the_bypass_dies_with_the_staff_session(): void
    {
        [$staff, $user] = $this->pendingCustomerAndStaff();

        Auth::guard('staff')->login($staff);
        Auth::shouldUse('staff');
        app(ImpersonateCustomerUser::class)($user);
        Auth::shouldUse('web');

        // Le flag de session seul ne suffit pas : sans session staff vivante,
        // le bypass tombe (protection contre une session qui traînerait).
        Auth::guard('staff')->logout();

        $this->assertFalse(ProAccess::isImpersonating());
        $this->assertNotNull(ProAccess::denialReason($user));
    }

    /**
     * Client « pending » + e-mail non vérifié + un membre du staff, tel que le
     * cas réel rencontré en back-office.
     *
     * @return array{0: Staff, 1: User}
     */
    private function pendingCustomerAndStaff(): array
    {
        $staff = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'admin' => true,
        ]);

        $user = User::create([
            'name' => 'Tortank Pikachu',
            'email' => 'pending@example.test',
            'password' => Hash::make('password'),
            'email_verified_at' => null,
        ]);

        $customer = Customer::create([
            'first_name' => 'Tortank',
            'last_name' => 'Pikachu',
            'company_name' => 'ACME SARL',
            'pko_status' => 'pending',
            'sirene_status' => 'active',
        ]);

        $customer->users()->attach($user);

        return [$staff, $user];
    }
}
