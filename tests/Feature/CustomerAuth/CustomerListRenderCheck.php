<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Tests\TestCase;

class CustomerListRenderCheck extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_customer_list_renders_with_anonymize_action(): void
    {
        $staff = Staff::create([
            'first_name' => 'Anon',
            'last_name' => 'Render',
            'email' => 'anon-render@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');

        $this->get('/admin/customers')
            ->assertOk()
            ->assertSee('Anonymiser');
    }
}
