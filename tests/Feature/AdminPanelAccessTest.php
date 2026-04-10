<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    public function test_admin_panel_redirects_guests_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect();
    }

    public function test_admin_login_page_is_reachable(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
    }
}
