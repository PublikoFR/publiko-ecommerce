<?php

declare(strict_types=1);

namespace Pko\Pennylane\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Pko\Pennylane\Mail\InvoiceFinalizedMail;
use Pko\Pennylane\Models\PennylaneInvoice;

final class EmailInvoiceCommand extends Command
{
    protected $signature = 'pennylane:email-invoice {invoice : ID local du document}';

    protected $description = 'Met en file une facture ou un avoir finalisé jamais envoyé.';

    public function handle(): int
    {
        $invoice = PennylaneInvoice::find($this->argument('invoice'));
        if (! $invoice || ! $invoice->isFinalized() || ! $invoice->pennylane_id) {
            $this->error('Document finalisé introuvable.');

            return self::FAILURE;
        }
        if ($invoice->emailed_at) {
            $this->info('Document déjà envoyé : aucun nouvel envoi.');

            return self::SUCCESS;
        }
        $mail = new InvoiceFinalizedMail((int) $invoice->id);
        if (! $mail->shouldSend()) {
            $this->error("Le modèle d'e-mail est désactivé.");

            return self::FAILURE;
        }
        Mail::queue($mail);
        $this->info('Document mis en file. Le PDF sera récupéré au moment de l’envoi.');

        return self::SUCCESS;
    }
}
