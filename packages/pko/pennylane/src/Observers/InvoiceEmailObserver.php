<?php

declare(strict_types=1);

namespace Pko\Pennylane\Observers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Pko\Pennylane\Mail\InvoiceFinalizedMail;
use Pko\Pennylane\Models\PennylaneInvoice;

final class InvoiceEmailObserver
{
    public function created(PennylaneInvoice $invoice): void
    {
        $this->enqueue($invoice);
    }

    public function updated(PennylaneInvoice $invoice): void
    {
        if ($invoice->wasChanged('status')) {
            $this->enqueue($invoice);
        }
    }

    private function enqueue(PennylaneInvoice $invoice): void
    {
        if (! $invoice->isFinalized() || $invoice->emailed_at) {
            return;
        }

        $id = (int) $invoice->id;
        DB::afterCommit(static function () use ($id): void {
            try {
                Mail::queue(new InvoiceFinalizedMail($id));
            } catch (\Throwable) {
                // Une panne de file ne doit jamais déclasser une facture finalisée.
                Log::error('Mise en file du document impossible ; reprendre avec pennylane:email-invoice.', ['invoice_id' => $id]);
            }
        });
    }
}
