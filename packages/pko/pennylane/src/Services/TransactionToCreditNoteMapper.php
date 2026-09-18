<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Lunar\Models\Transaction;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Dto\CreateCreditNoteData;
use Pko\Pennylane\Dto\InvoiceLineData;

final class TransactionToCreditNoteMapper
{
    public function __construct(
        private readonly PennylaneClient $client,
        private readonly OrderToInvoiceMapper $invoiceMapper,
    ) {}

    public function build(
        Transaction $refund,
        int $pennylaneCustomerId,
        int $parentPennylaneInvoiceId,
    ): CreateCreditNoteData {
        $config = config('pennylane');
        $order = $refund->order;

        $externalReference = ($config['external_reference_prefix']['credit_note'] ?? 'refund_').$refund->id;
        $label = 'Remboursement commande '.($order->reference ?: (string) $order->id);

        return new CreateCreditNoteData(
            pennylaneCustomerId: $pennylaneCustomerId,
            customerInvoiceTemplateId: $this->client->resolveTemplateId(),
            parentInvoicePennylaneId: $parentPennylaneInvoiceId,
            externalReference: $externalReference,
            date: Carbon::now()->toDateString(),
            currency: strtoupper((string) ($order->currency_code ?? 'EUR')),
            lines: $this->prorataLines($refund, $label),
            reason: 'Avoir automatique suite à remboursement ('.$refund->reference.')',
            language: (string) ($config['default_language'] ?? 'fr'),
        );
    }

    /**
     * Un remboursement Stripe est un montant TTC global. On le répartit au
     * prorata du TTC de chaque taux de TVA de la commande, pour que l'avoir
     * annule la bonne TVA quand la commande mélange des taux (produits à 20 %,
     * port exonéré…). Un remboursement total redonne exactement la facture.
     *
     * @return array<int,InvoiceLineData>
     */
    private function prorataLines(Transaction $refund, string $label): array
    {
        $order = $refund->order;
        $orderTotal = (int) $order->total->value;
        $refundCents = (int) $refund->amount->value;
        $ratio = $orderTotal > 0 ? min(1.0, $refundCents / $orderTotal) : 1.0;

        /** @var array<string,float> $taxableByRate HT par taux, en unité monétaire */
        $taxableByRate = [];
        foreach ($this->invoiceMapper->lines($order) as $line) {
            $key = (string) $line->vatRate;
            $taxableByRate[$key] = ($taxableByRate[$key] ?? 0.0) + $line->quantity * $line->unitAmount;
        }

        $lines = [];
        foreach ($taxableByRate as $rate => $taxable) {
            $lines[] = new InvoiceLineData(
                label: count($taxableByRate) > 1 ? sprintf('%s (TVA %s %%)', $label, $rate) : $label,
                quantity: 1.0,
                unitAmount: -round($taxable * $ratio, 6),
                vatRate: (float) $rate,
                unit: 'service',
            );
        }

        return $lines;
    }
}
