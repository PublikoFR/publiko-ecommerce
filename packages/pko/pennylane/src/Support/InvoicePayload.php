<?php

declare(strict_types=1);

namespace Pko\Pennylane\Support;

use Illuminate\Support\Number;

/**
 * Lecture du `payload_snapshot` envoyé à Pennylane, pour l'affichage admin :
 * lignes typées et totaux recalculés en cents.
 *
 * Les totaux sont une reconstitution à partir de ce qui a été envoyé, pas le
 * montant retourné par Pennylane (qui arrondit ligne à ligne de son côté) :
 * l'écart éventuel est d'au plus quelques centimes.
 */
final class InvoicePayload
{
    /**
     * @param  array<string,mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public static function from(mixed $snapshot): self
    {
        if ($snapshot instanceof \ArrayObject) {
            $snapshot = $snapshot->getArrayCopy();
        }

        return new self(is_array($snapshot) ? $snapshot : []);
    }

    public function currency(): string
    {
        return (string) ($this->payload['currency'] ?? 'EUR');
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return json_decode((string) json_encode($this->payload), true) ?: [];
    }

    public static function money(int $cents, string $currency = 'EUR'): string
    {
        return (string) Number::currency($cents / 100, $currency, 'fr');
    }

    public function get(string $key): mixed
    {
        return $this->payload[$key] ?? null;
    }

    /**
     * @return list<array{label: string, quantity: float, unit: string, unit_cents: int, vat_rate: float, total_cents: int}>
     */
    public function lines(): array
    {
        $lines = $this->payload['invoice_lines'] ?? [];
        if (! is_iterable($lines)) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            $line = $line instanceof \ArrayObject ? $line->getArrayCopy() : (array) $line;
            $quantity = (float) ($line['quantity'] ?? 0);
            $unitPrice = (float) ($line['raw_currency_unit_price'] ?? 0);

            $rows[] = [
                'label' => (string) ($line['label'] ?? '—'),
                'quantity' => $quantity,
                'unit' => (string) ($line['unit'] ?? 'piece'),
                'unit_cents' => (int) round($unitPrice * 100),
                'vat_rate' => self::vatPercentage((string) ($line['vat_rate'] ?? '')),
                'total_cents' => (int) round($quantity * $unitPrice * 100),
            ];
        }

        return $rows;
    }

    /**
     * @return array{subtotal: int, tax: int, total: int}
     */
    public function totals(): array
    {
        $subtotal = 0;
        $tax = 0;
        foreach ($this->lines() as $line) {
            $subtotal += $line['total_cents'];
            $tax += (int) round($line['total_cents'] * $line['vat_rate'] / 100);
        }

        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax];
    }

    /**
     * `FR_200` → 20.0, `FR_55` → 5.5, `exempt` ou code inconnu → 0.
     */
    public static function vatPercentage(string $code): float
    {
        if (preg_match('/^[A-Z]{2}_(\d+)$/', $code, $matches) !== 1) {
            return 0.0;
        }

        return (int) $matches[1] / 10;
    }
}
