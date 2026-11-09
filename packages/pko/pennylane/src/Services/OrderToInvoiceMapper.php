<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use App\Support\Ecotax;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Dto\CreateInvoiceData;
use Pko\Pennylane\Dto\InvoiceLineData;
use Pko\Pennylane\Models\PennylaneDeliveryNote;
use Pko\Pennylane\Support\VatRate;

final class OrderToInvoiceMapper
{
    public function __construct(private readonly PennylaneClient $client) {}

    public function build(Order $order, int $pennylaneCustomerId): CreateInvoiceData
    {
        $config = config('pennylane');

        $externalReference = ($config['external_reference_prefix']['invoice'] ?? 'order_').$order->id;

        // Date d'émission = jour de la synchro, pas celui de la commande :
        // Pennylane refuse (422) de finaliser une facture antérieure à la
        // dernière facture finalisée (chronologie légale de la numérotation).
        // Dater à la commande bloquerait tout backfill, et toute facture dont
        // le job passe après celle d'une commande plus récente (retry, worker
        // en retard). La référence de commande reste dans l'objet du PDF.
        $date = Carbon::now()->toDateString();

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
            freeText: $this->deliveryNoteMention($order),
            language: (string) ($config['default_language'] ?? 'fr'),
            draft: false,
        );
    }

    /**
     * Lignes HT de la commande, remises déduites, port compris.
     *
     * Une ligne produit avec éco-participation figée est ventilée en deux
     * lignes au même taux : le produit (HT remisé − éco-taxe) puis
     * « Éco-participation – <produit> ». Le total HT et la base de chaque taux
     * sont inchangés (docs/packages/pennylane.md, « Éco-participation »).
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
            $label = $this->lineLabel($line);
            // Taux calculé sur la ligne d'origine : produit et éco-participation
            // partagent la même TVA, la ventilation ne change pas la base taxable.
            $vatRate = VatRate::fromAmounts($taxable, (int) $line->tax_total->value);
            $ecotax = $line->type === 'shipping' ? 0 : $this->ecotaxCents($line, $taxable);

            $lines[] = new InvoiceLineData(
                label: $label,
                quantity: (float) $quantity,
                unitAmount: ($taxable - $ecotax) / $quantity / 100,
                vatRate: $vatRate,
                unit: $line->type === 'shipping' ? 'service' : 'piece',
            );

            if ($ecotax > 0) {
                $lines[] = new InvoiceLineData(
                    label: 'Éco-participation – '.$label,
                    quantity: (float) $quantity,
                    unitAmount: $ecotax / $quantity / 100,
                    vatRate: $vatRate,
                    unit: 'piece',
                );
            }
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

    /**
     * Rattache la facture au bon de livraison qui justifie l'expédition.
     */
    private function deliveryNoteMention(Order $order): ?string
    {
        $note = PennylaneDeliveryNote::query()
            ->where('order_id', $order->id)
            ->where('status', PennylaneDeliveryNote::STATUS_FINALIZED)
            ->first();

        if (! $note?->pennylane_document_number) {
            return null;
        }

        $date = $note->synced_at?->format('d/m/Y');

        return 'Bon de livraison n° '.$note->pennylane_document_number.($date ? ' du '.$date : '');
    }

    /**
     * Part éco-participation (cents HT) incluse dans le HT remisé de la ligne
     * (modèle « dont », valeur figée à la commande). La remise ne s'impute
     * jamais sur l'éco-taxe : elle reste entière tant que le HT la couvre.
     */
    private function ecotaxCents(OrderLine $line, int $taxable): int
    {
        $ecotax = Ecotax::forOrderLine($line);
        if ($ecotax <= 0 || $taxable <= 0) {
            return 0;
        }

        if ($taxable < $ecotax) {
            Log::warning('Pennylane : éco-participation supérieure au HT remisé de la ligne, plafonnée.', [
                'order_id' => $line->order_id,
                'order_line_id' => $line->id,
                'taxable_cents' => $taxable,
                'ecotax_cents' => $ecotax,
            ]);

            return $taxable;
        }

        return $ecotax;
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
