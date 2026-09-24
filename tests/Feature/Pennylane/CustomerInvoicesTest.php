<?php

declare(strict_types=1);

namespace Tests\Feature\Pennylane;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\Transaction;
use Pko\Account\Livewire\InvoicesPage;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\Pennylane\Api\Exceptions\InvoicePdfNotReady;
use Pko\Pennylane\Api\Exceptions\PennylaneException;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;
use Pko\Pennylane\Mail\InvoiceFinalizedMail;
use Pko\Pennylane\Models\PennylaneInvoice;
use Pko\Pennylane\Services\InvoicePdfFetcher;
use Pko\Pennylane\Services\OrderDocuments;
use Tests\TestCase;

class CustomerInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private Mailer $mailer;

    private User $user;

    private Order $order;

    private const PDF = "%PDF-1.4\nexample PDF";

    private const PUBLIC_URL = 'https://files.example.test/private.pdf?encrypted_id=secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeHttp();
        $this->mailer = Mail::mailer('array');
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
        config(['pennylane.enabled' => true, 'pennylane.api_token' => 'fake-token']);
        $customer = Customer::factory()->create(['pko_status' => 'approved']);
        $this->user = User::factory()->create();
        $customer->users()->attach($this->user);
        $this->order = Order::withoutEvents(fn () => Order::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $this->user->id,
            'currency_code' => 'EUR',
            'compare_currency_code' => 'EUR',
            'total' => 12345,
        ]));
    }

    private function fakeHttp(array $overrides = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($overrides + [
            '*/customer_invoices/*' => Http::response(['public_file_url' => self::PUBLIC_URL]),
            self::PUBLIC_URL => Http::response(self::PDF, 200, ['Content-Type' => 'application/pdf']),
        ]);
        app()->forgetInstance(PennylaneClient::class);
        app()->forgetInstance(CustomerInvoicesResource::class);
    }

    private function invoice(array $attributes = []): PennylaneInvoice
    {
        return PennylaneInvoice::withoutEvents(fn () => PennylaneInvoice::create($attributes + [
            'order_id' => $this->order->id,
            'external_reference' => uniqid('test_'),
            'pennylane_id' => random_int(1000, 9999999),
            'pennylane_invoice_number' => 'F-TEST-1',
            'status' => PennylaneInvoice::STATUS_FINALIZED,
            'type' => PennylaneInvoice::TYPE_INVOICE,
            'payload_snapshot' => ['date' => '2026-09-18'],
        ]));
    }

    public function test_download_is_private_and_never_redirects_to_provider(): void
    {
        $invoice = $this->invoice();
        $response = $this->actingAs($this->user)->get(route('pennylane.customer.pdf', $invoice->id));
        $response->assertOk()->assertDownload('Facture-F-TEST-1.pdf')->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(self::PDF, $response->streamedContent());
        $this->assertStringNotContainsString('encrypted_id', (string) $response->headers);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_another_customer_or_unfinalized_document_returns_404_without_http(): void
    {
        $other = Customer::factory()->create();
        $foreign = Order::withoutEvents(fn () => Order::factory()->create(['customer_id' => $other->id]));
        $foreignInvoice = $this->invoice(['order_id' => $foreign->id]);
        $draft = $this->invoice(['status' => PennylaneInvoice::STATUS_DRAFT]);
        $this->actingAs($this->user)->get(route('pennylane.customer.pdf', $foreignInvoice->id))->assertNotFound();
        $this->get(route('pennylane.customer.pdf', $draft->id))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('pennylane.customer.pdf', $this->invoice()->id))->assertRedirect();
        Http::assertNothingSent();
    }

    public function test_list_contains_only_own_finalized_documents_without_provider_urls(): void
    {
        $this->invoice();
        $transaction = Transaction::withoutEvents(fn () => Transaction::factory()->create(['order_id' => $this->order->id, 'amount' => 2345, 'type' => 'refund']));
        $credit = $this->invoice(['type' => 'credit_note', 'transaction_id' => $transaction->id, 'pennylane_invoice_number' => 'A-TEST-2']);
        $this->invoice(['status' => 'draft', 'pennylane_invoice_number' => 'DRAFT-HIDDEN']);
        $foreign = Order::withoutEvents(fn () => Order::factory()->create(['customer_id' => Customer::factory()->create()->id]));
        $this->invoice(['order_id' => $foreign->id, 'pennylane_invoice_number' => 'FOREIGN-HIDDEN']);
        $this->actingAs($this->user);
        Livewire::test(InvoicesPage::class)->assertSee('F-TEST-1')->assertSee('A-TEST-2')
            ->assertSee('123,45 EUR')->assertSee('−23,45 EUR')->assertSee('Avoir')
            ->assertDontSee('DRAFT-HIDDEN')->assertDontSee('FOREIGN-HIDDEN')
            ->assertDontSee('encrypted_id')->assertDontSee('pennylane.com');
        Http::assertNothingSent();
        $this->get(route('pennylane.customer.pdf', $credit->id))->assertDownload('Avoir-A-TEST-2.pdf');
    }

    public function test_empty_state(): void
    {
        $this->actingAs($this->user);
        Livewire::test(InvoicesPage::class)->assertSee('Aucune facture disponible');
    }

    public function test_invoices_page_is_rendered_without_account_sidebar(): void
    {
        $this->actingAs($this->user);

        $this->get(route('account.invoices'))->assertOk()
            ->assertSee('Mes factures')->assertDontSee('Connecté·e en tant que');
        $this->get(route('account.orders'))->assertOk()->assertSee('Connecté·e en tant que');
    }

    public function test_admin_signed_download_still_works_and_signature_is_required(): void
    {
        $this->invoice();
        $this->actingAs($this->staff(), 'staff');

        $this->get(route('pennylane.invoice.pdf', $this->order->id))->assertForbidden();
        $this->get(URL::temporarySignedRoute('pennylane.invoice.pdf', now()->addMinutes(5), ['order' => $this->order->id]))
            ->assertOk()->assertDownload('Facture-F-TEST-1.pdf');
    }

    public function test_admin_download_is_refused_to_a_storefront_customer_even_with_a_valid_signature(): void
    {
        $this->invoice();
        $url = URL::temporarySignedRoute('pennylane.invoice.pdf', now()->addMinutes(5), ['order' => $this->order->id]);

        // Le back-office authentifie sur le garde « staff » : une session client
        // (garde « web ») ne doit pas suffire, même si un lien signé a fuité.
        $response = $this->actingAs($this->user)->get($url);

        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_order_documents_expose_signed_admin_links_only(): void
    {
        $this->invoice();
        $transaction = Transaction::withoutEvents(fn () => Transaction::factory()->create(['order_id' => $this->order->id, 'type' => 'refund']));
        $this->invoice(['type' => 'credit_note', 'transaction_id' => $transaction->id, 'external_reference' => 'refund_'.$transaction->id, 'pennylane_invoice_number' => 'F-TEST-2']);

        $documents = OrderDocuments::forOrder($this->order);

        $this->assertSame('ready', $documents['invoice']['state']);
        $this->assertStringContainsString('/admin/pennylane/invoice/'.$this->order->id.'/pdf', $documents['invoice']['url']);
        $this->assertStringContainsString('signature=', $documents['invoice']['url']);
        $this->assertCount(1, $documents['credit_notes']);
        $this->assertSame('F-TEST-2', $documents['credit_notes'][0]['number']);
        $this->assertStringNotContainsString('pennylane.com', json_encode($documents));
    }

    public function test_missing_pdf_returns_safe_retryable_response(): void
    {
        $this->fakeHttp(['*/customer_invoices/*' => Http::response(['public_file_url' => ''])]);
        $this->actingAs($this->user)->get(route('pennylane.customer.pdf', $this->invoice()->id))
            ->assertStatus(503)->assertHeader('Retry-After', '60')->assertDontSee('encrypted_id');
    }

    public function test_http_409_is_a_dedicated_not_ready_exception(): void
    {
        $this->fakeHttp(['*/customer_invoices/*' => Http::response([], 409)]);
        $this->expectException(InvoicePdfNotReady::class);
        app(InvoicePdfFetcher::class)->fetch(999);
    }

    public function test_non_pdf_is_rejected_without_leaking_the_url(): void
    {
        $this->fakeHttp([self::PUBLIC_URL => Http::response('<html>error</html>')]);
        try {
            app(InvoicePdfFetcher::class)->fetch(999);
            $this->fail('Non-PDF accepted');
        } catch (PennylaneException $exception) {
            $this->assertStringNotContainsString('encrypted_id', $exception->getMessage());
        }
    }

    public function test_only_transition_to_finalized_queues_mail(): void
    {
        $invoice = $this->invoice(['status' => 'draft']);
        $invoice->update(['status' => 'finalized']);
        $invoice->update(['synced_at' => now()]);
        Mail::assertQueued(InvoiceFinalizedMail::class, 1);
        $this->assertNull($invoice->fresh()->emailed_at);
        Http::assertNothingSent();
    }

    public function test_invoice_and_credit_note_send_pdf_once_and_survive_queue_serialization(): void
    {
        OrderAddress::factory()->create(['order_id' => $this->order->id, 'type' => 'billing', 'contact_email' => 'billing@example.test']);
        foreach (['invoice', 'credit_note'] as $type) {
            $invoice = $this->invoice(['type' => $type]);
            $mail = unserialize(serialize(new InvoiceFinalizedMail($invoice->id)));
            $sent = $mail->send($this->mailer);
            $message = $sent->getOriginalMessage();
            $this->assertSame('billing@example.test', $message->getTo()[0]->getAddress());
            $this->assertCount(1, $message->getAttachments());
            $this->assertSame(self::PDF, $message->getAttachments()[0]->getBody());
            $this->assertSame($invoice->pdfFilename(), $message->getAttachments()[0]->getFilename());
            $this->assertStringNotContainsString('encrypted_id', $message->toString());
            $this->assertNotNull($invoice->fresh()->emailed_at);
            $this->assertNull((new InvoiceFinalizedMail($invoice->id))->send($this->mailer));
        }
        $this->assertCount(2, $this->mailer->getSymfonyTransport()->messages());
        Http::assertSentCount(4);
    }

    public function test_not_ready_releases_claim_and_retry_succeeds_using_user_email(): void
    {
        $invoice = $this->invoice();
        $this->fakeHttp(['*/customer_invoices/*' => Http::sequence()->push(['public_file_url' => null])->push(['public_file_url' => self::PUBLIC_URL])]);
        $mail = new InvoiceFinalizedMail($invoice->id);
        $this->assertSame([60, 180, 600, 1800, 3600], $mail->backoff);
        try {
            $mail->send($this->mailer);
            $this->fail('PDF not ready should throw');
        } catch (InvoicePdfNotReady) {
            $this->assertNull($invoice->fresh()->emailed_at);
            $this->assertNull($invoice->fresh()->email_claimed_at);
        }
        $sent = (new InvoiceFinalizedMail($invoice->id))->send($this->mailer);
        $this->assertSame($this->user->email, $sent->getOriginalMessage()->getTo()[0]->getAddress());
        $this->assertNotNull($invoice->fresh()->emailed_at);
    }

    public function test_active_claim_prevents_concurrent_send_and_expired_claim_recovers(): void
    {
        $invoice = $this->invoice(['email_claimed_at' => now(), 'email_claim_token' => 'other-worker']);
        try {
            (new InvoiceFinalizedMail($invoice->id))->send($this->mailer);
            $this->fail('Active claim should prevent sending');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('déjà en cours', $exception->getMessage());
        }
        Http::assertNothingSent();
        $invoice->updateQuietly(['email_claimed_at' => now()->subMinutes(11)]);
        $this->assertNotNull((new InvoiceFinalizedMail($invoice->id))->send($this->mailer));
    }

    public function test_transport_failure_releases_claim_without_marking_sent(): void
    {
        $invoice = $this->invoice();
        $mailer = \Mockery::mock(\Illuminate\Contracts\Mail\Mailer::class);
        $mailer->shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        try {
            (new InvoiceFinalizedMail($invoice->id))->send($mailer);
            $this->fail('Transport failure should propagate to the worker');
        } catch (\RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
            $this->assertNull($invoice->fresh()->emailed_at);
            $this->assertNull($invoice->fresh()->email_claim_token);
        }
    }

    public function test_credit_note_admin_download_remains_signed(): void
    {
        $transaction = Transaction::withoutEvents(fn () => Transaction::factory()->create(['order_id' => $this->order->id, 'type' => 'refund']));
        $this->invoice(['type' => 'credit_note', 'transaction_id' => $transaction->id]);
        $this->actingAs($this->staff(), 'staff')->get(URL::temporarySignedRoute('pennylane.credit-note.pdf', now()->addMinutes(5), ['transaction' => $transaction->id]))
            ->assertOk()->assertDownload('Avoir-F-TEST-1.pdf');
    }

    public function test_command_requeues_only_unsent_finalized_documents(): void
    {
        $invoice = $this->invoice();
        $this->artisan('pennylane:email-invoice', ['invoice' => $invoice->id])->assertSuccessful();
        Mail::assertQueued(InvoiceFinalizedMail::class, 1);
        $invoice->updateQuietly(['emailed_at' => now()]);
        $this->artisan('pennylane:email-invoice', ['invoice' => $invoice->id])->assertSuccessful();
        Mail::assertQueued(InvoiceFinalizedMail::class, 1);
        Http::assertNothingSent();
    }

    public function test_disabled_template_does_not_send_or_mark_sent(): void
    {
        $invoice = $this->invoice();
        MailTemplate::where('key', 'billing.invoice_finalized')->update(['enabled' => false]);
        $this->assertNull((new InvoiceFinalizedMail($invoice->id))->send($this->mailer));
        $this->assertNull($invoice->fresh()->emailed_at);
        Http::assertNothingSent();
    }

    private function staff(): Staff
    {
        return Staff::create([
            'first_name' => 'Compta',
            'last_name' => 'Admin',
            'email' => 'compta-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
    }
}
