<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Customer;
use Tests\TestCase;

/**
 * Garde-fou : la fiche client (edit) doit se rendre sans 500. Régression d'origine
 * de la session : CustomerProfileExtension::swapTitleComponent plantait sur
 * "Component::$container must not be accessed" (schéma lazy évalué hors container).
 */
class CustomerEditPageRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $staff = Staff::create([
            'first_name' => 'Edit',
            'last_name' => 'Render',
            'email' => 'edit-render@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');
    }

    public function test_customer_edit_page_renders(): void
    {
        $customer = Customer::query()->firstOrFail();

        $this->get("/admin/customers/{$customer->id}/edit")
            ->assertOk()
            ->assertSee('SIRET');
    }

    public function test_customer_list_and_create_render(): void
    {
        $this->get('/admin/customers')->assertOk();
        $this->get('/admin/customers/create')->assertOk();
    }

    public function test_customer_view_page_renders_with_combined_tabs(): void
    {
        $customer = Customer::query()->firstOrFail();

        // PkoViewCustomer fusionne contenu + relations : l'onglet de contenu porte
        // le label 'Informations' (getContentTabLabel), preuve que le mode combiné
        // est actif (sinon aucun onglet de contenu n'est rendu).
        $this->get("/admin/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('Informations');
    }
}
