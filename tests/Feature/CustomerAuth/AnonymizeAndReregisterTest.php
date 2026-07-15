<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Pko\CustomerAuth\Actions\AnonymizeCustomer;
use Tests\TestCase;

/**
 * Vérifie que l'anonymisation RGPD libère l'email pour une ré-inscription,
 * et que les nouveaux champs pko_* sont bien réinitialisés.
 */
class AnonymizeAndReregisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function makeCustomerWithUser(string $email): array
    {
        $user = User::create([
            'name' => 'Entreprise Test',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'ACME SARL',
            'sirene_status' => 'active',
            'pko_status' => 'active',
            'pko_street' => '10 rue de la Paix',
            'pko_postcode' => '75001',
            'pko_city' => 'Paris',
            'pko_country' => 'FR',
            'sepa_enabled' => true,
        ]);

        $group = CustomerGroup::firstOrCreate(['handle' => 'installateurs'], ['name' => 'Installateurs']);
        $customer->customerGroups()->attach($group);
        $customer->users()->attach($user);

        return [$user, $customer];
    }

    public function test_anonymize_resets_pko_fields(): void
    {
        [, $customer] = $this->makeCustomerWithUser('jean@example.test');

        app(AnonymizeCustomer::class)->handle($customer);

        $fresh = Customer::find($customer->id);
        $this->assertSame('banned', $fresh->pko_status);
        $this->assertNull($fresh->pko_street);
        $this->assertNull($fresh->pko_postcode);
        $this->assertNull($fresh->pko_city);
        $this->assertNull($fresh->pko_country);
        $this->assertFalse((bool) $fresh->sepa_enabled);
    }

    public function test_reregistration_with_same_email_succeeds_after_anonymization(): void
    {
        $email = 'jean@example.test';
        [, $customer] = $this->makeCustomerWithUser($email);

        app(AnonymizeCustomer::class)->handle($customer);

        // L'ancien User est supprimé → la contrainte unique(users.email) est libérée.
        $this->assertNull(User::where('email', $email)->first(), 'L\'ancien User doit être supprimé.');

        // Ré-inscription avec le même email ne doit pas lever de contrainte unique.
        $newUser = User::create([
            'name' => 'Nouveau Client',
            'email' => $email,
            'password' => Hash::make('newpassword'),
        ]);

        $this->assertNotNull($newUser->id, 'Le nouvel User doit être créé sans erreur.');
        $this->assertSame($email, $newUser->email);
    }
}
