<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Actions\ValidateCustomerAccount;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoEditCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoListCustomers;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoViewCustomer;
use Pko\CustomerAuth\Livewire\LoginPage;
use Pko\CustomerAuth\Mail\AccountActivatedMail;
use Tests\TestCase;

/**
 * FEAT-006 : chaque compte pro est validé manuellement par l'équipe. La
 * validation (action « Valider le compte » ou Select « Statut compte ») passe le
 * compte `pending` → `active` et envoie AccountActivatedMail, une seule fois.
 */
class ManualAccountValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    public function test_validation_activates_the_account_and_sends_the_mail_once(): void
    {
        $customer = $this->account(verified: true);

        app(ValidateCustomerAccount::class)($customer);

        $this->assertSame('active', $customer->fresh()->pko_status);
        Mail::assertSent(AccountActivatedMail::class, 1);
        Mail::assertSent(AccountActivatedMail::class, fn (AccountActivatedMail $mail): bool => $mail->hasTo('pro@example.test')
            && $mail->values['login_url'] === route('login'));

        // Un enregistrement ultérieur de la fiche (statut inchangé) ne renvoie rien.
        $customer->fresh()->update(['company_name' => 'ACME 2']);
        Mail::assertSent(AccountActivatedMail::class, 1);
    }

    public function test_validated_account_can_log_in(): void
    {
        $customer = $this->account(verified: true);
        app(ValidateCustomerAccount::class)($customer);

        Livewire::test(LoginPage::class)
            ->set('email', 'pro@example.test')
            ->set('password', 'secret-password')
            ->call('authenticate')
            ->assertRedirect('/compte');
    }

    public function test_unverified_email_blocks_the_validation(): void
    {
        $customer = $this->account(verified: false);

        $this->assertNotNull(ValidateCustomerAccount::blockReason($customer));

        try {
            app(ValidateCustomerAccount::class)($customer);
            $this->fail('La validation d\'un compte à e-mail non confirmé doit être refusée.');
        } catch (\DomainException) {
        }

        $this->assertSame('pending', $customer->fresh()->pko_status);
        Mail::assertNotSent(AccountActivatedMail::class);
    }

    public function test_status_change_through_the_select_also_sends_the_mail(): void
    {
        // Chemin « Select Statut compte » de la fiche : simple update du modèle.
        $customer = $this->account(verified: true);

        $customer->update(['pko_status' => 'active']);

        Mail::assertSent(AccountActivatedMail::class, 1);
    }

    public function test_reactivating_a_banned_account_sends_nothing(): void
    {
        $customer = $this->account(verified: true);
        $customer->update(['pko_status' => 'banned']);

        $customer->update(['pko_status' => 'active']);

        Mail::assertNotSent(AccountActivatedMail::class);
    }

    public function test_disabled_template_is_respected(): void
    {
        \Pko\MailTemplates\Models\MailTemplate::query()
            ->where('key', 'account.activated')
            ->update(['enabled' => false]);
        $customer = $this->account(verified: true);

        app(ValidateCustomerAccount::class)($customer);

        $this->assertSame('active', $customer->fresh()->pko_status);
        Mail::assertNotSent(AccountActivatedMail::class);
    }

    public function test_admin_row_action_validates_the_account(): void
    {
        $this->actingAsStaff();
        $customer = $this->account(verified: true);

        Livewire::test(PkoListCustomers::class)
            ->assertTableActionVisible('validate_account', $customer)
            ->callTableAction('validate_account', $customer)
            ->assertHasNoTableActionErrors();

        $this->assertSame('active', $customer->fresh()->pko_status);
        Mail::assertSent(AccountActivatedMail::class, 1);
    }

    public function test_admin_row_action_is_disabled_while_email_is_unverified(): void
    {
        $this->actingAsStaff();
        $customer = $this->account(verified: false);

        Livewire::test(PkoListCustomers::class)
            ->assertTableActionDisabled('validate_account', $customer);
    }

    public function test_admin_row_action_is_hidden_for_active_accounts(): void
    {
        $this->actingAsStaff();
        $customer = $this->account(verified: true);
        $customer->update(['pko_status' => 'active']);

        Livewire::test(PkoListCustomers::class)
            ->assertTableActionHidden('validate_account', $customer);
    }

    public function test_admin_bulk_action_validates_only_eligible_accounts(): void
    {
        $this->actingAsStaff();
        $eligible = $this->account(verified: true);
        $unverified = $this->account(verified: false, email: 'other@example.test');

        Livewire::test(PkoListCustomers::class)
            ->callTableBulkAction('validate_accounts', [$eligible, $unverified]);

        $this->assertSame('active', $eligible->fresh()->pko_status);
        $this->assertSame('pending', $unverified->fresh()->pko_status);
        Mail::assertSent(AccountActivatedMail::class, 1);
    }

    public function test_pending_validation_filter(): void
    {
        $this->actingAsStaff();
        $pending = $this->account(verified: true);
        $active = $this->account(verified: true, email: 'active@example.test');
        $active->update(['pko_status' => 'active']);

        Livewire::test(PkoListCustomers::class)
            ->filterTable('pending_validation')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_header_action_on_the_customer_page(): void
    {
        $this->actingAsStaff();
        $customer = $this->account(verified: true);

        Livewire::test(PkoViewCustomer::class, ['record' => $customer->getRouteKey()])
            ->callAction('validate_account');

        $this->assertSame('active', $customer->fresh()->pko_status);
        Mail::assertSent(AccountActivatedMail::class, 1);
    }

    public function test_edit_form_refuses_activation_while_email_is_unverified(): void
    {
        $this->actingAsStaff();
        $customer = $this->account(verified: false);

        Livewire::test(PkoEditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm(['pko_status' => 'active'])
            ->call('save')
            ->assertHasFormErrors(['pko_status']);

        $this->assertSame('pending', $customer->fresh()->pko_status);
    }

    public function test_edit_form_select_validates_a_verified_account(): void
    {
        $this->actingAsStaff();
        $customer = $this->account(verified: true);

        Livewire::test(PkoEditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm(['pko_status' => 'active'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('active', $customer->fresh()->pko_status);
        Mail::assertSent(AccountActivatedMail::class, 1);
    }

    private function account(bool $verified, string $email = 'pro@example.test'): Customer
    {
        $user = User::create([
            'name' => 'Jean Dupont',
            'email' => $email,
            'password' => Hash::make('secret-password'),
        ]);
        // `email_verified_at` n'est pas mass-assignable sur User.
        $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        $customer = Customer::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'ACME SAS',
            'pko_status' => 'pending',
            'sirene_status' => 'active',
        ]);
        $customer->users()->attach($user);

        return $customer->fresh();
    }

    private function actingAsStaff(): void
    {
        $staff = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'Validation',
            'email' => 'admin-validation@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');
    }
}
