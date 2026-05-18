<?php

declare(strict_types=1);

namespace Pko\Pennylane\Dto;

final class CreateInvoiceData
{
    /**
     * @param  array<int, InvoiceLineData>  $lines
     */
    public function __construct(
        public readonly int $pennylaneCustomerId,
        public readonly int $customerInvoiceTemplateId,
        public readonly string $externalReference,
        public readonly string $date,
        public readonly string $deadline,
        public readonly string $currency,
        public readonly array $lines,
        public readonly ?string $subject = null,
        public readonly ?string $description = null,
        public readonly ?string $freeText = null,
        public readonly string $language = 'fr',
        public readonly bool $draft = false,
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
            'deadline' => $this->deadline,
            'currency' => $this->currency,
            'language' => $this->language,
            'draft' => $this->draft,
            'pdf_invoice_subject' => $this->subject,
            'pdf_description' => $this->description,
            'pdf_invoice_free_text' => $this->freeText,
            'line_items' => array_map(fn (InvoiceLineData $l) => $l->toArray(), $this->lines),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
