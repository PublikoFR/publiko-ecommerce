<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Support\CustomerGroupGuard;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Tests\TestCase;

class CustomerGroupDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_default_group_is_protected(): void
    {
        $group = CustomerGroup::where('default', true)->firstOrFail();
        $this->assertNotNull(CustomerGroupGuard::blockReason($group));
        $this->assertFalse(CustomerGroupGuard::isDeletable($group));
    }

    /**
     * Le groupe utilisé par l'inscription professionnelle est protégé.
     *
     * On lit le handle depuis la config plutôt que de l'écrire en dur : ce test
     * visait « installateurs », qui n'est PAS le groupe configuré — il ne passait
     * que parce que ce groupe portait des tarifs seedés, bloquants à l'époque.
     * Depuis que les tarifs se cascadent, il faut viser le bon groupe.
     */
    public function test_pro_group_is_protected(): void
    {
        $handle = (string) config('customer-auth.default_customer_group_handle', 'nouveau-client');

        $group = CustomerGroup::firstOrCreate(
            ['handle' => $handle],
            ['name' => 'Groupe pro', 'default' => false]
        );

        $reason = CustomerGroupGuard::blockReason($group);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('inscription professionnelle', $reason);
    }

    /**
     * Contrepartie : un groupe qui porte des tarifs n'est plus bloqué, il est
     * cascadé — l'utilisateur est averti dans la modale de confirmation.
     */
    public function test_group_with_prices_is_no_longer_blocked(): void
    {
        $group = CustomerGroup::where('handle', 'installateurs')->first();

        if ($group === null) {
            $this->markTestSkipped('Le seeder ne crée pas de groupe « installateurs ».');
        }

        $this->assertNull(CustomerGroupGuard::blockReason($group));
    }

    public function test_group_referenced_only_by_customers_is_deletable(): void
    {
        // Un groupe uniquement référencé par des clients n'est PAS bloqué : les
        // clients sont détachés et réattribués au groupe par défaut.
        $group = CustomerGroup::create(['name' => 'Grossistes', 'handle' => 'grossistes', 'default' => false]);

        $customer = Customer::create(['first_name' => 'A', 'last_name' => 'B', 'company_name' => 'ACME']);
        $customer->customerGroups()->attach($group);

        $this->assertNull(CustomerGroupGuard::blockReason($group));
    }

    public function test_customers_are_reassigned_to_default_group_on_deletion(): void
    {
        $group = CustomerGroup::create(['name' => 'Grossistes', 'handle' => 'grossistes', 'default' => false]);

        $customer = Customer::create(['first_name' => 'A', 'last_name' => 'B', 'company_name' => 'ACME']);
        $customer->customerGroups()->attach($group);

        $reassigned = CustomerGroupGuard::reassignCustomersToDefault($group);
        $this->assertSame(1, $reassigned);

        $group->delete();
        $this->assertNull(CustomerGroup::find($group->id));

        // Le client a perdu le groupe supprimé et récupéré le groupe par défaut.
        $handles = $customer->refresh()->customerGroups()->pluck('handle')->toArray();
        $this->assertNotContains('grossistes', $handles);
        $this->assertContains(
            (string) config('customer-auth.default_customer_group_handle', 'nouveau-client'),
            $handles
        );
    }

    public function test_clean_custom_group_is_deletable(): void
    {
        $group = CustomerGroup::create(['name' => 'Vide', 'handle' => 'vide', 'default' => false]);

        $this->assertNull(CustomerGroupGuard::blockReason($group));
        $this->assertTrue(CustomerGroupGuard::isDeletable($group));

        // Se supprime sans violation de FK.
        $group->delete();
        $this->assertNull(CustomerGroup::find($group->id));
    }
}
