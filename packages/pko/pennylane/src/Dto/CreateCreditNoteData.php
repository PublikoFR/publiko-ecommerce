<?php

declare(strict_types=1);

namespace Pko\Pennylane\Dto;

final class CreateCreditNoteData
{
    /**
     * @param  array<int, InvoiceLineData>  $lines
     */
    public function __construct(
        public readonly int $pennylaneCustomerId,
        public readonly int $customerInvoiceTemplateId,
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
            'credit_note' => true,
            'parent_invoice_id' => $this->parentInvoicePennylaneId,
            'external_reference' => $this->externalReference,
            'date' => $this->date,
            'deadline' => $this->date,
            'currency' => $this->currency,
            'language' => $this->language,
            'draft' => false,
            'pdf_description' => $this->reason,
            'line_items' => array_map(fn (InvoiceLineData $l) => $l->toArray(), $this->lines),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
