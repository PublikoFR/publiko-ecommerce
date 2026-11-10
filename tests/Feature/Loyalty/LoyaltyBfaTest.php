<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Account\Livewire\LoyaltyPage;
use Pko\Loyalty\Contracts\BfaCreditNoteIssuer;
use Pko\Loyalty\Enums\GiftType;
use Pko\Loyalty\Filament\Pages\BfaPayouts;
use Pko\Loyalty\Models\BfaPayout;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\Loyalty\Services\BfaCalculator;
use Pko\Loyalty\Services\BfaPayoutEmitter;
use Pko\Loyalty\Services\LoyaltyManager;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;
use Pko\Pennylane\Api\Resources\CustomersResource;
use Pko\Pennylane\Models\PennylaneCustomer;
use Pko\Pennylane\Services\CustomerMapper;
use Pko\Pennylane\Services\LoyaltyBfaCreditNoteIssuer;
use Pko\StorefrontCms\Models\Setting as StorefrontSetting;
use Tests\TestCase;

/**
 * BFA : tranches par déblocage de palier, pré-calcul de fin d'année et
 * émission idempotente des avoirs Pennylane autonomes.
 */
class LoyaltyBfaTest extends TestCase
{
    use RefreshDatabase;

    private LoyaltyTier $silver;

    private LoyaltyTier $gold;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Http::preventStrayRequests();
        Mail::fake();
        $this->configurePennylane(enabled: false);
        StorefrontSetting::set('admin_email', 'admin@example.test');
        LoyaltyTier::query()->update(['active' => false]); // neutralise les paliers de démo

        // 1 € HT = 1 point (ratio par défaut).
        $this->silver = LoyaltyTier::create(['name' => 'Argent', 'points_required' => 100, 'gift_type' => GiftType::Bfa, 'bfa_fixed_amount' => 50_00, 'bfa_percent' => '2.00', 'active' => true]);
        $this->gold = LoyaltyTier::create(['name' => 'Or', 'points_required' => 1000, 'gift_type' => GiftType::Bfa, 'bfa_percent' => '3.00', 'active' => true]);
    }

    public function test_each_bfa_unlock_opens_a_tranche_until_the_next_one(): void
    {
        $customer = $this->scenario2025();

        $tranches = app(BfaCalculator::class)->tranches($customer->id, 2025);

        $this->assertCount(2, $tranches);

        // Tranche Argent : B (300 €) + C (600 €, qui débloque Or : passée AVANT le déblocage).
        // La commande A, qui ouvre la tranche, n'en fait pas partie.
        $this->assertSame($this->silver->id, $tranches[0]->tierId);
        $this->assertSame('2025-02-01', $tranches[0]->start->toDateString());
        $this->assertSame('2025-04-01', $tranches[0]->end->toDateString());
        $this->assertSame(900_00, $tranches[0]->revenueHt);
        $this->assertSame(50_00 + 18_00, $tranches[0]->amount);

        // Tranche Or : D (1 000 €) jusqu'au 31/12.
        $this->assertSame('2025-12-31', $tranches[1]->end->toDateString());
        $this->assertSame(1000_00, $tranches[1]->revenueHt);
        $this->assertSame(30_00, $tranches[1]->amount);

        $this->assertSame(98_00, app(BfaCalculator::class)->acquiredToDate($customer->id, 2025));
    }

    public function test_revenue_is_net_of_refunds_and_ignores_other_years(): void
    {
        $customer = $this->scenario2025();
        $orderB = Order::where('customer_id', $customer->id)->where('sub_total', 300_00)->firstOrFail();

        $this->travelTo('2025-11-01 10:00:00');
        $this->makeRefund($orderB, 150_00); // moitié du TTC

        $this->travelTo('2026-01-15 10:00:00');
        $this->makePlacedOrder($customer, 5000_00); // année suivante : hors BFA 2025

        $tranches = app(BfaCalculator::class)->tranches($customer->id, 2025);

        $this->assertSame(750_00, $tranches[0]->revenueHt);
        $this->assertSame(50_00 + 15_00, $tranches[0]->amount);
        $this->assertSame(1000_00, $tranches[1]->revenueHt);
    }

    public function test_customer_snapshot_exposes_the_real_time_estimate(): void
    {
        $customer = $this->scenario2025();
        $this->travelTo('2025-12-15 10:00:00');

        $bfa = app(LoyaltyManager::class)->getCustomerSnapshot($customer->id)['bfa'];

        $this->assertSame(2025, $bfa['year']);
        $this->assertSame(98_00, $bfa['acquired']);
        $this->assertCount(2, $bfa['tranches']);

        $other = $this->makeCustomer();
        $this->assertNull(app(LoyaltyManager::class)->getCustomerSnapshot($other->id)['bfa']);
    }

    public function test_precompute_keeps_adjustments_and_never_touches_issued_rows(): void
    {
        $customer = $this->scenario2025();
        $this->travelTo('2026-01-05 09:00:00');
        $calculator = app(BfaCalculator::class);

        $this->assertSame(2, $calculator->precompute(2025));
        $this->assertSame(2, $calculator->precompute(2025)); // idempotent
        $this->assertSame(2, BfaPayout::count());

        [$silver, $gold] = BfaPayout::orderBy('period_start')->get()->all();
        $this->assertSame(68_00, $silver->computed_amount);
        $this->assertSame('bfa_2025_'.$silver->gift_history_id, $silver->external_reference);

        $silver->update(['adjusted_amount' => 70_00]);
        $gold->update(['status' => BfaPayout::STATUS_ISSUED, 'computed_amount' => 1]);

        // Nouvelle commande 2025 enregistrée tardivement dans la tranche Or.
        $this->travelTo('2025-12-20 10:00:00');
        $this->makePlacedOrder($customer, 100_00);
        $this->travelTo('2026-01-05 09:00:00');
        $calculator->precompute(2025);

        $this->assertSame(70_00, $silver->fresh()->amountToIssue());
        $this->assertSame(1, $gold->fresh()->computed_amount);

        // Déblocage disparu (remboursement) : le brouillon est purgé.
        GiftHistory::whereKey($silver->gift_history_id)->delete();
        $calculator->precompute(2025);
        $this->assertNull(BfaPayout::find($silver->id));
        $this->assertNotNull(BfaPayout::find($gold->id));
    }

    public function test_emission_creates_a_standalone_finalized_credit_note_once(): void
    {
        $customer = $this->scenario2025();
        $this->travelTo('2026-01-05 09:00:00');
        app(BfaCalculator::class)->precompute(2025);
        $payout = BfaPayout::orderBy('period_start')->firstOrFail();
        $payout->update(['adjusted_amount' => 70_00]);

        $this->configurePennylane(enabled: true);
        PennylaneCustomer::create([
            'lunar_customer_id' => $customer->id,
            'external_reference' => 'lunar_cust_'.$customer->id,
            'pennylane_customer_id' => 555,
        ]);

        Http::fake([
            '*/customer_invoices/9001/finalize' => Http::response(['id' => 9001, 'draft' => false, 'invoice_number' => 'AV-2026-0001']),
            '*/customer_invoices?*' => Http::response(['items' => []]),
            '*/customer_invoices' => Http::response(['id' => 9001, 'draft' => true]),
        ]);

        $emitter = app(BfaPayoutEmitter::class);
        $this->assertSame(BfaPayoutEmitter::RESULT_ISSUED, $emitter->emit($payout));
        $this->assertSame(BfaPayoutEmitter::RESULT_SKIPPED, $emitter->emit($payout->fresh()));

        $payout->refresh();
        $this->assertSame(BfaPayout::STATUS_ISSUED, $payout->status);
        $this->assertSame(9001, $payout->pennylane_id);
        $this->assertSame('AV-2026-0001', $payout->credit_note_number);
        $this->assertNotNull($payout->issued_at);

        Http::assertSentCount(3); // recherche, création, finalisation — une seule fois
        Http::assertSent(function (Request $request) use ($payout): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/customer_invoices')) {
                return false;
            }
            $line = $request['invoice_lines'][0];

            return $request['customer_id'] === 555
                && $request['external_reference'] === $payout->external_reference
                && $line['raw_currency_unit_price'] === '-70.00'
                && $line['vat_rate'] === 'FR_200';
        });
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'link_credit_note'));
    }

    public function test_failed_emission_is_recorded_and_retry_reuses_the_remote_document(): void
    {
        $customer = $this->scenario2025();
        $this->travelTo('2026-01-05 09:00:00');
        app(BfaCalculator::class)->precompute(2025);
        $payout = BfaPayout::orderBy('period_start')->firstOrFail();
        // Seconde tranche mise à zéro : seule la première est émise.
        BfaPayout::whereKeyNot($payout->id)->update(['adjusted_amount' => 0]);

        $this->configurePennylane(enabled: true);
        PennylaneCustomer::create([
            'lunar_customer_id' => $customer->id,
            'external_reference' => 'lunar_cust_'.$customer->id,
            'pennylane_customer_id' => 555,
        ]);

        // Avoir créé côté Pennylane, mais finalisation refusée.
        Http::fake([
            '*/customer_invoices/9001/finalize' => Http::sequence()
                ->push(['message' => 'Configurez la numérotation'], 422)
                ->push(['id' => 9001, 'draft' => false, 'invoice_number' => 'AV-1']),
            '*/customer_invoices?*' => Http::sequence()
                ->push(['items' => []])
                ->push(['items' => [['id' => 9001, 'draft' => true]]]),
            '*/customer_invoices' => Http::response(['id' => 9001, 'draft' => true]),
        ]);

        $emitter = app(BfaPayoutEmitter::class);
        $this->assertSame(BfaPayoutEmitter::RESULT_FAILED, $emitter->emit($payout));
        $this->assertSame(BfaPayout::STATUS_FAILED, $payout->fresh()->status);
        $this->assertNotNull($payout->fresh()->last_error);

        $counts = $emitter->emitYear(2025);
        $this->assertSame(1, $counts[BfaPayoutEmitter::RESULT_ISSUED]);
        $this->assertSame(0, $counts[BfaPayoutEmitter::RESULT_FAILED]);

        // Pas de seconde création : l'avoir existant a été retrouvé par sa référence.
        $creations = collect(Http::recorded())->filter(fn (array $pair): bool => $pair[0]->method() === 'POST' && str_ends_with($pair[0]->url(), '/customer_invoices'));
        $this->assertCount(1, $creations);
        $this->assertSame(BfaPayout::STATUS_ISSUED, $payout->fresh()->status);
        $this->assertNull($payout->fresh()->last_error);
    }

    public function test_open_year_and_zero_amounts_are_not_emitted(): void
    {
        $this->scenario2025();
        $this->travelTo('2025-12-20 09:00:00');
        app(BfaCalculator::class)->precompute(2025);

        $this->assertFalse(BfaPayoutEmitter::canIssueYear(2025));
        $this->assertSame(0, app(BfaPayoutEmitter::class)->emitYear(2025)['issued']);

        $this->travelTo('2026-01-05 09:00:00');
        $payout = BfaPayout::orderBy('period_start')->firstOrFail();
        $payout->update(['adjusted_amount' => 0]);
        $this->assertSame(BfaPayoutEmitter::RESULT_SKIPPED, app(BfaPayoutEmitter::class)->emit($payout));
        $this->assertSame(BfaPayout::STATUS_DRAFT, $payout->fresh()->status);

        Http::assertNothingSent();
    }

    public function test_admin_page_precomputes_adjusts_and_lists_the_tranches(): void
    {
        $this->scenario2025();
        $this->travelTo('2026-01-05 09:00:00');
        $this->actingAsStaff();

        $this->get(BfaPayouts::getUrl())->assertOk();

        $page = Livewire::test(BfaPayouts::class)
            ->callAction('precompute')
            ->assertCanSeeTableRecords(BfaPayout::all());

        $payout = BfaPayout::orderBy('period_start')->firstOrFail();
        $page->callTableAction('adjust', $payout, data: ['amount' => '72,50'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(72_50, $payout->fresh()->amountToIssue());

        // Retour au montant calculé : l'ajustement disparaît.
        $page->callTableAction('adjust', $payout, data: ['amount' => '68']);
        $this->assertNull($payout->fresh()->adjusted_amount);

        $page->assertTableActionVisible('emit', $payout)
            ->filterTable('year', '2026')
            ->assertCanNotSeeTableRecords(BfaPayout::all());
    }

    public function test_account_page_shows_the_bfa_acquired_to_date(): void
    {
        $customer = $this->scenario2025();
        $this->travelTo('2025-12-15 10:00:00');

        $this->actingAs($customer->users()->firstOrFail());

        Livewire::test(LoyaltyPage::class)
            ->assertSee('BFA acquis à ce jour : 98 € HT')
            ->assertSee('Versé en janvier 2026');
    }

    public function test_bfa_credit_note_issuer_is_bound_to_pennylane(): void
    {
        $this->assertInstanceOf(LoyaltyBfaCreditNoteIssuer::class, app(BfaCreditNoteIssuer::class));
    }

    public function test_percent_and_refund_arithmetic_stays_in_integer_cents(): void
    {
        $this->assertSame(3, BfaCalculator::percentOf(1_33, '2.50')); // 3,325 → 3
        $this->assertSame(0, BfaCalculator::percentOf(1000_00, '0.00'));
        $this->assertSame(66_67, BfaCalculator::netOfRefunds(100_00, 40_00, 120_00));
        $this->assertSame(0, BfaCalculator::netOfRefunds(100_00, 130_00, 120_00));
    }

    /**
     * A (150 €, 01/02) débloque Argent ; B (300 €, 01/03) ; C (600 €, 01/04)
     * débloque Or ; D (1 000 €, 01/06).
     */
    private function scenario2025(): Customer
    {
        $customer = $this->makeCustomer();

        foreach ([['2025-02-01', 150_00], ['2025-03-01', 300_00], ['2025-04-01', 600_00], ['2025-06-01', 1000_00]] as [$date, $amount]) {
            $this->travelTo($date.' 10:00:00');
            $this->makePlacedOrder($customer, $amount);
        }

        $this->assertSame(2, GiftHistory::where('customer_id', $customer->id)->where('year', 2025)->count());

        return $customer;
    }

    private function configurePennylane(bool $enabled): void
    {
        config([
            'pennylane.enabled' => $enabled,
            'pennylane.api_token' => $enabled ? 'test-token' : null,
            'pennylane.customer_invoice_template_id' => null,
            'pennylane.http.retry_times' => 1,
            'pennylane.http.retry_sleep_ms' => 1,
        ]);

        foreach ([PennylaneClient::class, CustomerInvoicesResource::class, CustomersResource::class, CustomerMapper::class] as $abstract) {
            app()->forgetInstance($abstract);
        }
    }

    private function actingAsStaff(): void
    {
        $staff = Staff::create([
            'first_name' => 'Bfa',
            'last_name' => 'Admin',
            'email' => 'bfa-admin@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');
        Filament::setCurrentPanel(Filament::getPanel('lunar'));
    }

    private function makeCustomer(): Customer
    {
        $customer = Customer::factory()->create(['company_name' => 'Client test']);
        $customer->users()->attach(User::factory()->create(['email' => 'client-'.uniqid().'@example.test']));

        return $customer;
    }

    private function makeRefund(Order $order, int $amountCents): Transaction
    {
        return Transaction::create([
            'order_id' => $order->id,
            'success' => true,
            'type' => 'refund',
            'driver' => 'offline',
            'amount' => $amountCents,
            'reference' => 'test-'.uniqid(),
            'status' => 'refunded',
            'card_type' => 'offline',
            'last_four' => '0000',
        ]);
    }

    private function makePlacedOrder(Customer $customer, int $subTotalCents): Order
    {
        return Order::factory()->create([
            'customer_id' => $customer->id,
            'sub_total' => $subTotalCents,
            'total' => $subTotalCents,
            'placed_at' => now(),
        ]);
    }
}
