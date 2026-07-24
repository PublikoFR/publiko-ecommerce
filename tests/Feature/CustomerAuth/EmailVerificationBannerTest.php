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

/**
 * Le bandeau « vérifiez votre adresse e-mail » (layout storefront) ne doit
 * concerner QUE les comptes encore `pending` : pour eux, valider l'e-mail est
 * l'étape qui active le compte. Un compte déjà `active` mais dont l'e-mail n'est
 * pas « vérifié » au sens Laravel (activé en back-office) ne doit pas être nagué.
 */
class EmailVerificationBannerTest extends TestCase
{
    use RefreshDatabase;

    private const BANNER = 'vérifier votre adresse e-mail';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_active_account_does_not_see_the_verification_banner(): void
    {
        $user = $this->accountWithStatus('active@example.test', 'active');

        $response = $this->actingAs($user)->get('/compte');

        $response->assertOk()->assertDontSee(self::BANNER, false);
    }

    public function test_freshly_registered_pending_account_still_sees_the_banner(): void
    {
        $user = $this->accountWithStatus('pending@example.test', 'pending');

        // Auto-login post-inscription (flag) : le compte pending accède au front
        // et doit voir le rappel de vérification.
        $response = $this->actingAs($user)
            ->withSession(['pko.just_registered' => true])
            ->get('/compte');

        $response->assertOk()->assertSee(self::BANNER, false);
    }

    private function accountWithStatus(string $email, string $status): User
    {
        $user = User::create([
            'name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => null,
        ]);

        $customer = Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Pro',
            'pko_status' => $status,
            'sirene_status' => 'active',
        ]);
        $customer->users()->attach($user);
        $customer->customerGroups()->attach(
            CustomerGroup::firstOrCreate(
                ['handle' => (string) config('customer-auth.default_customer_group_handle', 'nouveau-client')],
                ['name' => 'Nouveau client']
            )
        );

        return $user;
    }
}
