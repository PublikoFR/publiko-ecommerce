<?php

declare(strict_types=1);

namespace Pko\Pennylane\Dto;

use Pko\Pennylane\Support\VatRate;

final class InvoiceLineData
{
    /**
     * @param  float  $unitAmount  Prix unitaire HT, remise déduite, en unité monétaire (négatif pour un avoir).
     * @param  float  $vatRate  Taux de TVA en pourcentage (20.0), traduit en code Pennylane à la sérialisation.
     */
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
            // L'API accepte jusqu'à 6 décimales : un prix unitaire remisé ne tombe
            // pas forcément sur un centime, l'arrondir fausserait le total de ligne.
            'raw_currency_unit_price' => self::decimal($this->unitAmount),
            'vat_rate' => VatRate::toPennylaneCode($this->vatRate),
        ];
    }

    private static function decimal(float $amount): string
    {
        $formatted = rtrim(number_format($amount, 6, '.', ''), '0');

        // Garder au moins deux décimales pour la lisibilité côté Pennylane.
        [$int, $dec] = explode('.', $formatted) + [1 => ''];

        return $int.'.'.str_pad($dec, 2, '0');
    }
}
