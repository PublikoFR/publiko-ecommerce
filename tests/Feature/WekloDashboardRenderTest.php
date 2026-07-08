<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Tests\TestCase;

class WekloDashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_weklo_dashboard_renders_for_authenticated_staff(): void
    {
        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'dash-test@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('Tableau de bord');
        $response->assertSee('wk-dash');
        $response->assertSee('wkDashboard', false);
        // Identité staff injectée dans la topbar (renderHook USER_MENU_BEFORE).
        $response->assertSee('wk-user-identity');
        $response->assertSee('Test Admin');
    }
}
