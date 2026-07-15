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

    public function test_pro_group_is_protected(): void
    {
        $group = CustomerGroup::firstOrCreate(
            ['handle' => 'installateurs'],
            ['name' => 'Installateurs', 'default' => false]
        );
        $this->assertNotNull(CustomerGroupGuard::blockReason($group));
    }

    public function test_referenced_custom_group_is_blocked(): void
    {
        $group = CustomerGroup::create(['name' => 'Grossistes', 'handle' => 'grossistes', 'default' => false]);

        $customer = Customer::create(['first_name' => 'A', 'last_name' => 'B', 'company_name' => 'ACME']);
        $customer->customerGroups()->attach($group);

        $reason = CustomerGroupGuard::blockReason($group);
        $this->assertNotNull($reason);
        $this->assertStringContainsString('client', $reason);
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
