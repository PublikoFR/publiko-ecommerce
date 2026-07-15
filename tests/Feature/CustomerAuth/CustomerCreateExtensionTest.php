<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Filament\Extensions\CustomerCreateExtension;
use Tests\TestCase;

class CustomerCreateExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_before_creation_strips_user_email_from_data(): void
    {
        $ext = app(CustomerCreateExtension::class);

        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'ACME SARL',
            'user_email' => 'jean@example.test',
        ];

        $filtered = $ext->beforeCreation($data);

        $this->assertArrayNotHasKey('user_email', $filtered, 'user_email ne doit pas être dans le payload Customer::create()');
        $this->assertSame('Jean', $filtered['first_name']);
    }

    public function test_after_creation_creates_user_attaches_to_customer_and_sends_invite(): void
    {
        Password::shouldReceive('broker')->once()->andReturnSelf();
        Password::shouldReceive('sendResetLink')->once()->with(['email' => 'jean@example.test'])->andReturn(Password::RESET_LINK_SENT);

        $ext = app(CustomerCreateExtension::class);

        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'ACME SARL',
            'user_email' => 'jean@example.test',
        ];

        // Simule le cycle beforeCreation → Customer::create → afterCreation
        $filtered = $ext->beforeCreation($data);

        $customer = Customer::create([
            'first_name' => $filtered['first_name'],
            'last_name' => $filtered['last_name'],
            'company_name' => $filtered['company_name'],
        ]);

        $result = $ext->afterCreation($customer, $filtered);

        $this->assertSame($customer->id, $result->id);

        $user = User::where('email', 'jean@example.test')->first();
        $this->assertNotNull($user, 'Le User doit être créé.');
        $this->assertSame('Jean Dupont', $user->name);
        $this->assertSame(1, $customer->users()->count(), 'Le User doit être attaché au Customer.');
    }

    public function test_after_creation_without_email_skips_user_creation(): void
    {
        $ext = app(CustomerCreateExtension::class);

        $data = ['first_name' => 'Jean', 'last_name' => 'Dupont', 'company_name' => 'ACME'];

        // Pas d'appel beforeCreation → pendingEmail reste null
        $customer = Customer::create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'company_name' => 'ACME']);
        $ext->afterCreation($customer, $data);

        $this->assertSame(0, $customer->users()->count(), 'Aucun User ne doit être créé sans email.');
    }
}
