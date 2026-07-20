<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * La déconnexion est exemptée de CSRF (bootstrap/app.php) : un token périmé
     * (page SPA wire:navigate) ne doit pas renvoyer 419 et laisser l'utilisateur
     * connecté. Ici on POST SANS token → doit quand même déconnecter.
     */
    public function test_logout_succeeds_without_csrf_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->assertAuthenticated();

        $this->post('/deconnexion')->assertRedirect('/');

        $this->assertGuest();
    }
}
