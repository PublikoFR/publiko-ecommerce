<?php

declare(strict_types=1);

namespace Tests\Feature\Pennylane;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Order;
use Pko\Pennylane\Filament\Resources\PennylaneInvoiceResource\Pages\ViewPennylaneInvoice;
use Pko\Pennylane\Models\PennylaneInvoice;
use Pko\Pennylane\Support\InvoicePayload;
use Tests\TestCase;

class ViewPennylaneInvoicePageTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = [
        'date' => '2026-09-18',
        'deadline' => '2026-10-18',
        'currency' => 'EUR',
        'pdf_invoice_subject' => 'Commande 00000018',
        'invoice_lines' => [
            ['label' => 'Serrure électrique 12V', 'quantity' => 2, 'unit' => 'piece', 'vat_rate' => 'FR_200', 'raw_currency_unit_price' => '732.30'],
            ['label' => 'Livre technique', 'quantity' => 1, 'unit' => 'piece', 'vat_rate' => 'FR_55', 'raw_currency_unit_price' => '10.00'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'staff@example.test',
            'password' => 'secret123',
            'admin' => true,
        ]), 'staff');
        Gate::before(fn () => true);
    }

    private function invoice(array $attributes = []): PennylaneInvoice
    {
        $order = Order::withoutEvents(fn () => Order::factory()->create());

        return PennylaneInvoice::create([
            'order_id' => $order->id,
            'type' => PennylaneInvoice::TYPE_INVOICE,
            'pennylane_id' => 30045302599680,
            'pennylane_invoice_number' => 'F-2026-09-1',
            'external_reference' => 'order_'.$order->id,
            'status' => PennylaneInvoice::STATUS_FINALIZED,
            'payload_snapshot' => self::PAYLOAD,
            'synced_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_payload_lines_and_totals_are_computed_in_cents(): void
    {
        $payload = InvoicePayload::from(self::PAYLOAD);

        $this->assertSame(146460, $payload->lines()[0]['total_cents']);
        $this->assertSame(20.0, $payload->lines()[0]['vat_rate']);
        $this->assertSame(5.5, $payload->lines()[1]['vat_rate']);
        // 1 464,60 × 20 % = 292,92 ; 10,00 × 5,5 % = 0,55
        $this->assertSame(['subtotal' => 147460, 'tax' => 29347, 'total' => 176807], $payload->totals());
    }

    public function test_finalized_invoice_renders_lines_and_document_actions(): void
    {
        config(['pennylane.app_invoice_url' => 'https://app.pennylane.test/invoices/{id}']);
        $invoice = $this->invoice();

        Livewire::test(ViewPennylaneInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertOk()
            ->assertSee('Serrure électrique 12V')
            ->assertSee('Total TTC')
            ->assertDontSee('[object Object]')
            ->assertActionVisible('view_pdf')
            ->assertActionVisible('download_pdf')
            ->assertActionHasUrl('open_pennylane', 'https://app.pennylane.test/invoices/30045302599680')
            ->assertActionVisible('resync');
    }

    public function test_pdf_actions_are_hidden_until_the_invoice_is_finalized(): void
    {
        $invoice = $this->invoice([
            'status' => PennylaneInvoice::STATUS_FAILED,
            'pennylane_id' => null,
            'last_error' => 'HTTP 422 — customer_id invalide',
        ]);

        Livewire::test(ViewPennylaneInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertOk()
            ->assertSee('HTTP 422 — customer_id invalide')
            ->assertActionHidden('view_pdf')
            ->assertActionHidden('download_pdf')
            ->assertActionHidden('open_pennylane');
    }
}
