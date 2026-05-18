<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Pennylane\Api\Exceptions\PennylaneNotConfiguredException;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Dto\CreateCreditNoteData;
use Pko\Pennylane\Dto\InvoiceLineData;

final class TransactionToCreditNoteMapper
{
    public function __construct(private readonly PennylaneClient $client) {}

    public function build(
        Transaction $refund,
        int $pennylaneCustomerId,
        int $parentPennylaneInvoiceId,
    ): CreateCreditNoteData {
        $config = config('pennylane');

        $templateId = (int) ($this->client->resolveTemplateId() ?? 0);
        if ($templateId <= 0) {
            throw PennylaneNotConfiguredException::missingTemplate();
        }

        $externalReference = ($config['external_reference_prefix']['credit_note'] ?? 'refund_').$refund->id;

        $amountDecimal = round(((int) $refund->amount->value) / 100, 2);
        $order = $refund->order;

        $globalVat = $this->inferOrderVatRate($order);

        $unitWithoutVat = $globalVat > 0
            ? round($amountDecimal / (1 + $globalVat / 100), 2)
            : $amountDecimal;

        $line = new InvoiceLineData(
            label: 'Remboursement commande '.($order?->reference ?: (string) $order?->id),
            quantity: 1.0,
            unitAmount: $unitWithoutVat,
            vatRate: $globalVat,
            unit: 'service',
        );

        return new CreateCreditNoteData(
            pennylaneCustomerId: $pennylaneCustomerId,
            customerInvoiceTemplateId: $templateId,
            parentInvoicePennylaneId: $parentPennylaneInvoiceId,
            externalReference: $externalReference,
            date: Carbon::now()->toDateString(),
            currency: strtoupper((string) ($order?->currency_code ?? 'EUR')),
            lines: [$line],
            reason: 'Avoir automatique suite à remboursement ('.$refund->reference.')',
            language: (string) ($config['default_language'] ?? 'fr'),
        );
    }

    private function inferOrderVatRate(?Order $order): float
    {
        if (! $order) {
            return 0.0;
        }

        $breakdown = $order->tax_breakdown;
        $rate = 0.0;

        if (is_iterable($breakdown)) {
            foreach ($breakdown as $item) {
                if (is_object($item) && isset($item->percentage)) {
                    $rate = max($rate, (float) $item->percentage);
                } elseif (is_array($item) && isset($item['percentage'])) {
                    $rate = max($rate, (float) $item['percentage']);
                }
            }
        }

        return $rate;
    }
}
