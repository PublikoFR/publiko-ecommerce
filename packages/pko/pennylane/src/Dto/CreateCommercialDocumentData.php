<?php

declare(strict_types=1);

namespace Pko\Pennylane\Dto;

use Pko\Pennylane\Support\Language;

final class CreateCommercialDocumentData
{
    /**
     * @param  array<int,InvoiceLineData>  $lines
     */
    public function __construct(
        public readonly string $documentType,
        public readonly int $pennylaneCustomerId,
        public readonly string $externalReference,
        public readonly string $date,
        public readonly string $deadline,
        public readonly string $currency,
        public readonly array $lines,
        public readonly ?string $subject = null,
        public readonly ?string $description = null,
        public readonly string $language = 'fr',
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'document_type' => $this->documentType,
            'customer_id' => $this->pennylaneCustomerId,
            'external_reference' => $this->externalReference,
            'date' => $this->date,
            'deadline' => $this->deadline,
            'currency' => $this->currency,
            'language' => Language::toPennylane($this->language),
            'pdf_invoice_subject' => $this->subject,
            'pdf_description' => $this->description,
            'invoice_lines' => array_map(fn (InvoiceLineData $l) => $l->toArray(), $this->lines),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
