<?php

declare(strict_types=1);

namespace Pko\Pennylane\Dto;

final class InvoiceLineData
{
    public function __construct(
        public readonly string $label,
        public readonly float $quantity,
        public readonly float $unitAmount,
        public readonly float $vatRate,
        public readonly string $unit = 'piece',
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'currency_amount' => number_format($this->unitAmount, 2, '.', ''),
            'vat_rate' => $this->vatRate,
        ];
    }
}
