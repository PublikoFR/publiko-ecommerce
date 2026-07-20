<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Lunar\Models\CustomerGroup;
use Tests\TestCase;

class CustomerGroupPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $staff = Staff::create([
            'first_name' => 'CG',
            'last_name' => 'Render',
            'email' => 'cg-render@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');
    }

    public function test_customer_group_list_renders(): void
    {
        $this->get('/admin/customer-groups')->assertOk();
    }

    public function test_customer_group_edit_renders(): void
    {
        // Id dynamique : l'auto-increment MySQL n'est pas transactionnel, donc
        // on ne peut pas supposer que le premier groupe seedé porte l'id 1.
        $group = CustomerGroup::query()->firstOrFail();

        $this->get("/admin/customer-groups/{$group->id}/edit")->assertOk();
    }
}
