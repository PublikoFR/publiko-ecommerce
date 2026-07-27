<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoListCustomers;
use Tests\TestCase;

class CustomerListRenderCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function actAsStaff(): void
    {
        $staff = Staff::create([
            'first_name' => 'Anon',
            'last_name' => 'Render',
            'email' => 'anon-render@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');
    }

    public function test_customer_list_renders_with_rgpd_delete_action(): void
    {
        $this->actAsStaff();

        $this->get('/admin/customers')
            ->assertOk()
            ->assertSee('Supprimer (RGPD)');
    }

    public function test_customer_list_shows_clickable_email_below_name(): void
    {
        $this->actAsStaff();

        $customer = Customer::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);
        $user = User::factory()->create(['email' => 'jean.dupont@example.test']);
        $customer->users()->attach($user);

        // Recherche par e-mail (relation users) → le client doit remonter,
        // avec nom + prénom fusionnés et l'e-mail affiché sous le nom.
        // Le mailto n'est plus sur la cellule mais dans l'action « Envoyer un
        // e-mail » du dropdown (href mailto ; Livewire encode le « @ » en « &#64; »
        // → on assert le préfixe sans « @ »).
        Livewire::test(PkoListCustomers::class)
            ->searchTable('jean.dupont@example.test')
            ->assertCanSeeTableRecords([$customer])
            ->assertSee('Jean Dupont')
            ->assertSee('Envoyer un e-mail')
            ->assertSee('mailto:jean.dupont', false)
            // Le client a un utilisateur → l'action d'impersonation est présente
            // dans le dropdown des actions de ligne.
            ->assertSee('Se connecter en tant que');
    }
}
