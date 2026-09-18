<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Facades\URL;
use Lunar\Models\Order;
use Pko\Pennylane\Models\PennylaneInvoice;

/**
 * Documents Pennylane d'une commande, prêts à afficher dans la fiche admin :
 * facture et avoirs, avec un lien de téléchargement signé.
 *
 * Les liens pointent vers nos routes admin (proxy authentifié staff), jamais
 * vers `public_file_url` : ce lien Pennylane est public, il ne doit pas fuiter.
 */
final class OrderDocuments
{
    /** Durée de validité des liens signés affichés dans la page. */
    private const LINK_TTL_HOURS = 12;

    /**
     * @return array{invoice: array<string,mixed>|null, credit_notes: array<int, array<string,mixed>>}
     */
    public static function forOrder(Order $order): array
    {
        // La fiche l'appelle plusieurs fois par rendu (vue d'ensemble, bloc
        // Transactions, visibilité) : une seule requête par requête HTTP.
        return once(fn (): array => self::build($order));
    }

    /**
     * @return array{invoice: array<string,mixed>|null, credit_notes: array<int, array<string,mixed>>}
     */
    private static function build(Order $order): array
    {
        $records = PennylaneInvoice::query()->where('order_id', $order->id)->orderBy('id')->get();

        $invoice = $records->firstWhere('type', PennylaneInvoice::TYPE_INVOICE);

        return [
            'invoice' => $invoice ? self::row($invoice, $order) : null,
            'credit_notes' => $records
                ->where('type', PennylaneInvoice::TYPE_CREDIT_NOTE)
                ->map(fn (PennylaneInvoice $record): array => self::row($record, $order))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function row(PennylaneInvoice $record, Order $order): array
    {
        $isCredit = $record->type === PennylaneInvoice::TYPE_CREDIT_NOTE;
        $ready = $record->isFinalized() && $record->pennylane_id !== null;

        return [
            'type' => $isCredit ? 'Avoir' : 'Facture',
            'number' => $record->pennylane_invoice_number,
            'label' => trim(($isCredit ? 'Avoir' : 'Facture').' '.$record->pennylane_invoice_number),
            'transaction_id' => $record->transaction_id,
            'state' => match (true) {
                $ready => 'ready',
                $record->status === PennylaneInvoice::STATUS_FAILED => 'failed',
                default => 'pending',
            },
            'url' => $ready ? self::url($record, $order) : null,
        ];
    }

    private static function url(PennylaneInvoice $record, Order $order): string
    {
        $expires = now()->addHours(self::LINK_TTL_HOURS);

        return $record->type === PennylaneInvoice::TYPE_CREDIT_NOTE
            ? URL::temporarySignedRoute('pennylane.credit-note.pdf', $expires, ['transaction' => $record->transaction_id])
            : URL::temporarySignedRoute('pennylane.invoice.pdf', $expires, ['order' => $order->id]);
    }
}
