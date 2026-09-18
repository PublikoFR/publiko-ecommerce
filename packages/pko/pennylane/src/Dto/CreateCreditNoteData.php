<?php

declare(strict_types=1);

namespace Pko\Pennylane\Dto;

use Pko\Pennylane\Support\Language;

/**
 * En v2, un avoir est une facture client à montants négatifs, créée par le même
 * endpoint puis rattachée à la facture d'origine via `link_credit_note` : le
 * payload ne porte donc ni `credit_note` ni `parent_invoice_id`.
 */
final class CreateCreditNoteData
{
    /**
     * @param  array<int,InvoiceLineData>  $lines  Lignes à prix unitaire négatif.
     */
    public function __construct(
        public readonly int $pennylaneCustomerId,
        public readonly ?int $customerInvoiceTemplateId,
        public readonly int $parentInvoicePennylaneId,
        public readonly string $externalReference,
        public readonly string $date,
        public readonly string $currency,
        public readonly array $lines,
        public readonly ?string $reason = null,
        public readonly string $language = 'fr',
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'customer_id' => $this->pennylaneCustomerId,
            'customer_invoice_template_id' => $this->customerInvoiceTemplateId,
            'external_reference' => $this->externalReference,
            'date' => $this->date,
            'deadline' => $this->date,
            'currency' => $this->currency,
            'language' => Language::toPennylane($this->language),
            'draft' => false,
            'pdf_description' => $this->reason,
            'invoice_lines' => array_map(fn (InvoiceLineData $l) => $l->toArray(), $this->lines),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
