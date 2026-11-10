<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Pko\Account\Livewire\LoyaltyPage;
use Pko\Loyalty\Enums\GiftType;
use Pko\Loyalty\Filament\Resources\LoyaltyTierResource\Pages\CreateLoyaltyTier;
use Pko\Loyalty\Filament\Resources\LoyaltyTierResource\Pages\EditLoyaltyTier;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\LoyaltyTier;
use Tests\TestCase;

/**
 * Type de cadeau par palier : formulaire conditionnel (montants saisis en euros,
 * stockés en cents HT) et affichage dans l'espace fidélité.
 */
class LoyaltyTierGiftTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeded_bronze_tier_is_a_50_euro_voucher(): void
    {
        $bronze = LoyaltyTier::where('name', 'Bronze')->firstOrFail();

        $this->assertSame(GiftType::Voucher, $bronze->gift_type);
        $this->assertSame(50_00, $bronze->voucher_amount);
        $this->assertSame("Bon d'achat de 50 € HT", $bronze->giftLabel());
    }

    public function test_admin_creates_a_voucher_tier_in_euros_stored_in_cents(): void
    {
        $this->actingAsStaff();

        $this->fill(Livewire::test(CreateLoyaltyTier::class), [
            'name' => 'Platine',
            'points_required' => 5000,
            'gift_type' => GiftType::Voucher->value,
            'voucher_amount' => '75.50',
            'active' => true,
        ])
            ->assertFormFieldIsVisible('voucher_amount')
            ->assertFormFieldIsHidden('franco_threshold')
            ->assertFormFieldIsHidden('bfa_percent')
            ->call('create')
            ->assertHasNoFormErrors();

        $tier = LoyaltyTier::where('name', 'Platine')->firstOrFail();
        $this->assertSame(75_50, $tier->voucher_amount);
        $this->assertSame("Bon d'achat de 75,50 € HT", $tier->giftLabel());
    }

    public function test_voucher_amount_is_required_for_a_voucher_tier(): void
    {
        $this->actingAsStaff();

        $this->fill(Livewire::test(CreateLoyaltyTier::class), [
            'name' => 'Sans montant',
            'points_required' => 10,
            'gift_type' => GiftType::Voucher->value,
            'voucher_amount' => null,
        ])
            ->call('create')
            ->assertHasFormErrors(['voucher_amount' => 'required']);
    }

    public function test_switching_type_clears_the_fields_of_the_previous_type(): void
    {
        $this->actingAsStaff();
        $tier = LoyaltyTier::create([
            'name' => 'Diamant',
            'points_required' => 9000,
            'gift_type' => GiftType::Voucher,
            'voucher_amount' => 100_00,
            'active' => true,
        ]);

        $page = Livewire::test(EditLoyaltyTier::class, ['record' => $tier->getRouteKey()])
            ->assertFormSet(['voucher_amount' => '100.00']);

        $this->fill($page, [
            'gift_type' => GiftType::Bfa->value,
            'bfa_fixed_amount' => '150',
            'bfa_percent' => '2.5',
        ])
            ->assertFormFieldIsHidden('voucher_amount')
            ->call('save')
            ->assertHasNoFormErrors();

        $tier->refresh();
        $this->assertSame(GiftType::Bfa, $tier->gift_type);
        $this->assertNull($tier->voucher_amount);
        $this->assertSame(150_00, $tier->bfa_fixed_amount);
        $this->assertSame('BFA : 150 € HT + 2,5 %', $tier->giftLabel());
    }

    public function test_franco_tier_label(): void
    {
        $tier = new LoyaltyTier(['gift_type' => GiftType::Franco, 'franco_threshold' => 300_00]);

        $this->assertSame('Franco de port dès 300 € HT', $tier->giftLabel());
    }

    public function test_account_page_shows_the_voucher_code_amount_and_expiry(): void
    {
        Mail::fake();
        LoyaltyTier::query()->update(['active' => false]);
        LoyaltyTier::create(['name' => 'Bronze', 'points_required' => 50, 'gift_type' => GiftType::Voucher, 'voucher_amount' => 50_00, 'active' => true]);

        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $customer->users()->attach($user);

        Order::factory()->create([
            'customer_id' => $customer->id,
            'sub_total' => 100_00,
            'total' => 100_00,
            'placed_at' => now(),
        ]);

        $voucher = GiftHistory::where('customer_id', $customer->id)->firstOrFail()->voucher();
        $this->assertNotNull($voucher);

        $this->actingAs($user);

        Livewire::test(LoyaltyPage::class)
            ->assertSee($voucher['code'])
            ->assertSee("Bon d'achat de 50 € HT")
            ->assertSee($voucher['expires_at']->format('d/m/Y'))
            ->assertSee('Valable');
    }

    /**
     * Renseigne le formulaire champ par champ, comme la saisie en admin (le
     * Select `gift_type` est live). Historique : `fillForm()` ne faisait rien
     * tant que phpunit.xml ne forçait pas APP_ENV=testing (APP_ENV=local réel
     * dans le conteneur de dev) ; c'est corrigé, `set()` est conservé
     * (cf. docs/workflow.md).
     *
     * @param  array<string, mixed>  $state
     */
    private function fill(Testable $page, array $state): Testable
    {
        foreach ($state as $key => $value) {
            $page->set('data.'.$key, $value);
        }

        return $page;
    }

    private function actingAsStaff(): void
    {
        $staff = Staff::create([
            'first_name' => 'Fid',
            'last_name' => 'Admin',
            'email' => 'fid-admin@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');
        Filament::setCurrentPanel(Filament::getPanel('lunar'));
    }
}
