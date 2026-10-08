<?php

declare(strict_types=1);

namespace Tests\Feature\Pennylane;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Api\Resources\CommercialDocumentsResource;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;
use Pko\Pennylane\Jobs\SyncOrderDeliveryNoteJob;
use Pko\Pennylane\Jobs\SyncOrderInvoiceJob;
use Pko\Pennylane\Jobs\SyncRefundCreditNoteJob;
use Pko\Pennylane\Models\PennylaneCustomer;
use Pko\Pennylane\Models\PennylaneDeliveryNote;
use Pko\Pennylane\Models\PennylaneInvoice;
use Pko\Pennylane\Services\CreditNoteSynchronizer;
use Pko\Pennylane\Services\DeliveryNoteSynchronizer;
use Pko\Pennylane\Services\OrderToInvoiceMapper;
use Tests\TestCase;

class DeliveryNoteTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pennylane.enabled' => true,
            'pennylane.api_token' => 'fake-token',
            'pennylane.trigger_on_status' => 'dispatched',
            'pennylane.delivery_note.enabled' => true,
            'pennylane.delivery_note.trigger_on_status' => 'dispatched',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            '*/commercial_documents?*' => Http::response(['items' => []]),
            '*/commercial_documents' => Http::response(['id' => 501, 'document_number' => 'BL-2026-0007'], 201),
        ]);
        foreach ([PennylaneClient::class, CommercialDocumentsResource::class, CustomerInvoicesResource::class, DeliveryNoteSynchronizer::class] as $abstract) {
            app()->forgetInstance($abstract);
        }

        Currency::factory()->create(['code' => 'EUR', 'default' => true]);
        $customer = Customer::factory()->create();
        PennylaneCustomer::create([
            'lunar_customer_id' => $customer->id,
            'pennylane_customer_id' => 900,
            'external_reference' => 'lunar_cust_'.$customer->id,
        ]);

        $this->order = Order::withoutEvents(fn () => Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'payment-received',
            'currency_code' => 'EUR',
            'compare_currency_code' => 'EUR',
            'reference' => 'CMD-42',
        ]));
        DB::table('lunar_order_lines')->where('order_id', $this->order->id)->delete();
        $this->line('physical', 'Perceuse', 'SKU-1', 2, 10000, 2000);
        $this->line('shipping', 'Livraison', 'SHIP', 1, 990, 198);
    }

    private function line(string $type, string $description, string $identifier, int $quantity, int $subTotal, int $tax): void
    {
        DB::table('lunar_order_lines')->insert([
            'order_id' => $this->order->id,
            'purchasable_type' => 'product_variant',
            'purchasable_id' => 0,
            'type' => $type,
            'description' => $description,
            'identifier' => $identifier,
            'unit_price' => intdiv($subTotal, $quantity),
            'unit_quantity' => 1,
            'quantity' => $quantity,
            'sub_total' => $subTotal,
            'discount_total' => 0,
            'tax_breakdown' => '[]',
            'tax_total' => $tax,
            'total' => $subTotal + $tax,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_dispatch_status_queues_delivery_note_before_invoice(): void
    {
        Queue::fake();

        $this->order->update(['status' => 'dispatched']);

        Queue::assertPushed(SyncOrderDeliveryNoteJob::class);
        // La facture est émise par le job du bon de livraison, pas par l'observer.
        Queue::assertNotPushed(SyncOrderInvoiceJob::class);
    }

    public function test_invoice_trigger_unchanged_when_delivery_note_disabled(): void
    {
        config(['pennylane.delivery_note.enabled' => false]);
        Queue::fake();

        $this->order->update(['status' => 'dispatched']);

        Queue::assertPushed(SyncOrderInvoiceJob::class);
        Queue::assertNotPushed(SyncOrderDeliveryNoteJob::class);
    }

    public function test_delivery_note_job_creates_shipping_order_then_chains_invoice(): void
    {
        Queue::fake([SyncOrderInvoiceJob::class]);

        (new SyncOrderDeliveryNoteJob($this->order->id))->handle(app(DeliveryNoteSynchronizer::class));

        $note = PennylaneDeliveryNote::where('order_id', $this->order->id)->sole();
        $this->assertTrue($note->isFinalized());
        $this->assertSame('BL-2026-0007', $note->pennylane_document_number);
        $this->assertSame(501, $note->pennylane_id);

        Http::assertSent(function ($request): bool {
            if ($request->method() !== 'POST') {
                return false;
            }

            $labels = array_column($request['invoice_lines'], 'label');

            return $request['document_type'] === 'shipping_order'
                && $request['customer_id'] === 900
                && $request['external_reference'] === 'delivery_'.$this->order->id
                && $labels === ['Perceuse [SKU-1]'];
        });

        Queue::assertPushed(SyncOrderInvoiceJob::class, fn ($job) => $job->orderId === $this->order->id);
    }

    public function test_recovery_never_reuses_another_orders_document(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            '*/commercial_documents?*' => Http::response(['items' => [
                ['id' => 77, 'document_number' => 'BL-AUTRE', 'external_reference' => 'delivery_999999'],
            ], 'has_more' => false]),
            '*/commercial_documents' => Http::response(['id' => 501, 'document_number' => 'BL-2026-0007'], 201),
        ]);
        foreach ([PennylaneClient::class, CommercialDocumentsResource::class, DeliveryNoteSynchronizer::class] as $abstract) {
            app()->forgetInstance($abstract);
        }

        $note = app(DeliveryNoteSynchronizer::class)->sync($this->order);

        $this->assertSame('BL-2026-0007', $note->pennylane_document_number);
        Http::assertSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_recovery_reuses_document_created_by_a_previous_attempt(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            '*/commercial_documents?*' => Http::response(['items' => [
                ['id' => 501, 'document_number' => 'BL-2026-0007', 'external_reference' => 'delivery_'.$this->order->id],
            ], 'has_more' => false]),
        ]);
        foreach ([PennylaneClient::class, CommercialDocumentsResource::class, DeliveryNoteSynchronizer::class] as $abstract) {
            app()->forgetInstance($abstract);
        }

        $note = app(DeliveryNoteSynchronizer::class)->sync($this->order);

        $this->assertSame(501, $note->pennylane_id);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains(urldecode($request->url()), 'customer_id'));
    }

    public function test_missing_scope_fails_fast_and_still_invoices(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*/commercial_documents*' => Http::response(['error' => 'Forbidden'], 403)]);
        foreach ([PennylaneClient::class, CommercialDocumentsResource::class, DeliveryNoteSynchronizer::class] as $abstract) {
            app()->forgetInstance($abstract);
        }
        Queue::fake([SyncOrderInvoiceJob::class]);

        $job = new SyncOrderDeliveryNoteJob($this->order->id);
        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('fail')->once()->andReturnUsing(fn ($e) => $job->failed($e));
        $job->setJob($queueJob);

        $job->handle(app(DeliveryNoteSynchronizer::class));

        $this->assertSame(PennylaneDeliveryNote::STATUS_FAILED, PennylaneDeliveryNote::sole()->status);
        Queue::assertPushed(SyncOrderInvoiceJob::class);
    }

    public function test_delivery_note_sync_is_idempotent(): void
    {
        $synchronizer = app(DeliveryNoteSynchronizer::class);
        $synchronizer->sync($this->order);
        $synchronizer->sync($this->order);

        Http::assertSentCount(2); // une recherche de reprise + une création, pas de second document
        $this->assertSame(1, PennylaneDeliveryNote::count());
    }

    public function test_invoice_cites_delivery_note(): void
    {
        app(DeliveryNoteSynchronizer::class)->sync($this->order);

        $payload = app(OrderToInvoiceMapper::class)->build($this->order->fresh(), 900)->toArray();

        $this->assertStringStartsWith('Bon de livraison n° BL-2026-0007 du ', $payload['pdf_invoice_free_text']);
    }

    public function test_refund_before_invoice_is_deferred_without_retry(): void
    {
        $refund = Transaction::withoutEvents(fn () => Transaction::factory()->create([
            'order_id' => $this->order->id,
            'type' => 'refund',
            'success' => true,
        ]));

        $job = new SyncRefundCreditNoteJob($refund->id);
        $job->handle(app(CreditNoteSynchronizer::class));

        $this->assertSame(0, PennylaneInvoice::count());
        Http::assertNothingSent();
    }

    public function test_admin_downloads_delivery_note_pdf(): void
    {
        PennylaneDeliveryNote::create([
            'order_id' => $this->order->id,
            'pennylane_id' => 501,
            'pennylane_document_number' => 'BL-2026-0007',
            'external_reference' => 'delivery_'.$this->order->id,
            'status' => PennylaneDeliveryNote::STATUS_FINALIZED,
        ]);
        Http::fake([
            '*/commercial_documents/501' => Http::response(['id' => 501, 'public_file_url' => 'https://files.example.test/bl.pdf']),
            'https://files.example.test/bl.pdf' => Http::response("%PDF-1.4\nbl", 200),
        ]);

        $url = URL::signedRoute('pennylane.delivery-note.pdf', ['order' => $this->order->id]);

        $this->actingAs(Staff::factory()->create(), 'staff')
            ->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('Bon-de-livraison-BL-2026-0007.pdf');
    }
}
