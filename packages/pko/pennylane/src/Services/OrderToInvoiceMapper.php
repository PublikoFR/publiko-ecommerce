<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Pko\Pennylane\Api\Exceptions\PennylaneNotConfiguredException;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Dto\CreateInvoiceData;
use Pko\Pennylane\Dto\InvoiceLineData;

final class OrderToInvoiceMapper
{
    public function __construct(private readonly PennylaneClient $client) {}

    public function build(Order $order, int $pennylaneCustomerId): CreateInvoiceData
    {
        $config = config('pennylane');

        $templateId = (int) ($this->client->resolveTemplateId() ?? 0);
        if ($templateId <= 0) {
            throw PennylaneNotConfiguredException::missingTemplate();
        }

        $externalReference = ($config['external_reference_prefix']['invoice'] ?? 'order_').$order->id;

        $date = optional($order->placed_at ?? $order->created_at)->toDateString()
            ?? Carbon::now()->toDateString();

        $deadline = Carbon::parse($date)
            ->addDays((int) ($config['default_payment_deadline_days'] ?? 0))
            ->toDateString();

        $lines = $order->lines
            ->map(fn (OrderLine $line) => $this->mapLine($line))
            ->values()
            ->all();

        return new CreateInvoiceData(
            pennylaneCustomerId: $pennylaneCustomerId,
            customerInvoiceTemplateId: $templateId,
            externalReference: $externalReference,
            date: $date,
            deadline: $deadline,
            currency: strtoupper((string) $order->currency_code),
            lines: $lines,
            subject: $order->reference ? "Commande {$order->reference}" : null,
            description: $order->notes,
            language: (string) ($config['default_language'] ?? 'fr'),
            draft: false,
        );
    }

    private function mapLine(OrderLine $line): InvoiceLineData
    {
        $unitDecimal = $this->priceToDecimal($line->unit_price->value);
        $vatRate = $this->extractVatRate($line);

        return new InvoiceLineData(
            label: $this->lineLabel($line),
            quantity: (float) $line->quantity,
            unitAmount: $unitDecimal,
            vatRate: $vatRate,
            unit: $line->type === 'shipping' ? 'service' : 'piece',
        );
    }

    private function lineLabel(OrderLine $line): string
    {
        $label = trim((string) $line->description);
        if ($line->option) {
            $label .= ' ('.$line->option.')';
        }
        if ($line->identifier) {
            $label .= ' ['.$line->identifier.']';
        }

        return $label !== '' ? $label : ($line->identifier ?: 'Ligne');
    }

    private function extractVatRate(OrderLine $line): float
    {
        $breakdown = $line->tax_breakdown;

        $rate = 0.0;

        if (is_iterable($breakdown)) {
            foreach ($breakdown as $item) {
                $percentage = $this->extractPercentage($item);
                if ($percentage !== null) {
                    $rate = max($rate, $percentage);
                }
            }
        }

        return (float) $rate;
    }

    private function extractPercentage(mixed $item): ?float
    {
        if (is_object($item)) {
            if (isset($item->percentage)) {
                return (float) $item->percentage;
            }
            if (property_exists($item, 'percentage')) {
                return (float) $item->percentage;
            }
            if (method_exists($item, 'toArray')) {
                return $this->extractPercentage($item->toArray());
            }
        }

        if (is_array($item)) {
            return isset($item['percentage']) ? (float) $item['percentage'] : null;
        }

        return null;
    }

    private function priceToDecimal(int $valueInMinorUnits): float
    {
        return round($valueInMinorUnits / 100, 2);
    }
}
