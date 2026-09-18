<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Dto\CreateInvoiceData;
use Pko\Pennylane\Dto\InvoiceLineData;
use Pko\Pennylane\Support\VatRate;

final class OrderToInvoiceMapper
{
    public function __construct(private readonly PennylaneClient $client) {}

    public function build(Order $order, int $pennylaneCustomerId): CreateInvoiceData
    {
        $config = config('pennylane');

        $externalReference = ($config['external_reference_prefix']['invoice'] ?? 'order_').$order->id;

        $date = optional($order->placed_at ?? $order->created_at)->toDateString()
            ?? Carbon::now()->toDateString();

        $deadline = Carbon::parse($date)
            ->addDays((int) ($config['default_payment_deadline_days'] ?? 0))
            ->toDateString();

        return new CreateInvoiceData(
            pennylaneCustomerId: $pennylaneCustomerId,
            // Optionnel en v2 : sans modèle, Pennylane applique celui par défaut du compte.
            customerInvoiceTemplateId: $this->client->resolveTemplateId(),
            externalReference: $externalReference,
            date: $date,
            deadline: $deadline,
            currency: strtoupper((string) $order->currency_code),
            lines: $this->lines($order),
            subject: $order->reference ? "Commande {$order->reference}" : null,
            description: $order->notes,
            language: (string) ($config['default_language'] ?? 'fr'),
            draft: false,
        );
    }

    /**
     * Lignes HT de la commande, remises déduites, port compris.
     *
     * Réutilisé par l'avoir pour proratiser un remboursement par taux de TVA.
     *
     * @return array<int,InvoiceLineData>
     */
    public function lines(Order $order): array
    {
        $lines = [];
        $lineTaxCents = 0;
        $hasShippingLine = false;

        foreach ($order->lines as $line) {
            $lineTaxCents += (int) $line->tax_total->value;
            $hasShippingLine = $hasShippingLine || $line->type === 'shipping';

            $taxable = $this->taxableCents($line);
            // Livraison offerte : une ligne à zéro n'apporte rien à la facture.
            if ($taxable === 0 && $line->type === 'shipping') {
                continue;
            }

            $quantity = max(1, (int) $line->quantity);

            $lines[] = new InvoiceLineData(
                label: $this->lineLabel($line),
                quantity: (float) $quantity,
                unitAmount: $taxable / $quantity / 100,
                vatRate: VatRate::fromAmounts($taxable, (int) $line->tax_total->value),
                unit: $line->type === 'shipping' ? 'service' : 'piece',
            );
        }

        // Les commandes antérieures au checkout actuel portent le port dans
        // `shipping_total` sans ligne dédiée : l'omettre sous-facturerait.
        $shippingCents = (int) $order->shipping_total->value;
        if (! $hasShippingLine && $shippingCents > 0) {
            $lines[] = new InvoiceLineData(
                label: 'Frais de port',
                quantity: 1.0,
                unitAmount: $shippingCents / 100,
                vatRate: VatRate::fromAmounts($shippingCents, max(0, (int) $order->tax_total->value - $lineTaxCents)),
                unit: 'service',
            );
        }

        return $lines;
    }

    private function taxableCents(OrderLine $line): int
    {
        return (int) $line->sub_total->value - (int) $line->discount_total->value;
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
}
