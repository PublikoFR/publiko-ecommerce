<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
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
}
