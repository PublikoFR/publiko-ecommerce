<?php

declare(strict_types=1);

namespace Pko\Pennylane\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Str;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\MailTemplates\Support\TemplateResolver;
use Pko\Pennylane\Models\PennylaneInvoice;
use Pko\Pennylane\Services\InvoicePdfFetcher;

final class InvoiceFinalizedMail extends TemplatedMail implements ShouldQueue
{
    public int $tries = 6;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [60, 180, 600, 1800, 3600];

    public function __construct(public int $invoiceId)
    {
        $invoice = PennylaneInvoice::findOrFail($invoiceId);
        parent::__construct($invoice->type === PennylaneInvoice::TYPE_CREDIT_NOTE
            ? 'billing.credit_note_finalized' : 'billing.invoice_finalized');
        $this->onQueue((string) config('pennylane.queue', 'default'));
        $this->afterCommit();
    }

    /** Executed by SendQueuedMailable, never by the HTTP request that queues it. */
    public function send($mailer): ?SentMessage
    {
        $invoice = PennylaneInvoice::with(['order.billingAddress', 'order.user', 'order.customer.users'])->find($this->invoiceId);
        if (! $invoice || ! $invoice->isFinalized() || $invoice->emailed_at || ! $invoice->pennylane_id || ! $invoice->order) {
            return null;
        }

        $this->template = TemplateResolver::resolve($this->key, $this->templateLocale);
        if (! $this->shouldSend()) {
            return null;
        }

        $token = (string) Str::uuid();
        $claimed = PennylaneInvoice::whereKey($invoice->id)
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->whereNull('emailed_at')
            ->where(fn ($query) => $query->whereNull('email_claimed_at')
                ->orWhere('email_claimed_at', '<', now()->subMinutes(10)))
            ->update(['email_claimed_at' => now(), 'email_claim_token' => $token]);

        if (! $claimed) {
            if (PennylaneInvoice::whereKey($invoice->id)->whereNotNull('emailed_at')->exists()) {
                return null;
            }
            throw new \RuntimeException('Un envoi de ce document est déjà en cours.');
        }

        try {
            $order = $invoice->order;
            $recipient = $order->billingAddress?->contact_email;
            if (! filled($recipient)) {
                $recipient = $order->user?->email ?: $order->customer?->users->first()?->email;
            }
            if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Adresse e-mail de facturation absente ou invalide.');
            }

            $this->values = [
                'brand_name' => brand_name(),
                'first_name' => $order->billingAddress?->first_name ?: $order->customer?->first_name ?: '',
                'document_number' => $invoice->pennylane_invoice_number ?: (string) $invoice->id,
                'order_reference' => (string) ($order->reference ?: $order->id),
                'account_url' => route('account.invoices'),
            ];
            $this->to = [];
            $this->to($recipient);
            $this->rawAttachments = [];
            $this->attachData(app(InvoicePdfFetcher::class)->fetch((int) $invoice->pennylane_id), $invoice->pdfFilename(), ['mime' => 'application/pdf']);
            $sent = parent::send($mailer);
            if ($sent === null) {
                throw new \RuntimeException("L'envoi du document a été annulé par le transport.");
            }
            PennylaneInvoice::whereKey($invoice->id)->where('email_claim_token', $token)
                ->update(['emailed_at' => now()]);

            return $sent;
        } finally {
            // Token ownership prevents an expired worker from releasing another worker's lease.
            PennylaneInvoice::whereKey($invoice->id)->where('email_claim_token', $token)
                ->update(['email_claimed_at' => null, 'email_claim_token' => null]);
            $this->rawAttachments = [];
        }
    }
}
