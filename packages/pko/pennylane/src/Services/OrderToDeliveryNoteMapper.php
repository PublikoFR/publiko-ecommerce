<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Pko\Pennylane\Api\Resources\CommercialDocumentsResource;
use Pko\Pennylane\Dto\CreateCommercialDocumentData;
use Pko\Pennylane\Dto\InvoiceLineData;

final class OrderToDeliveryNoteMapper
{
    public function __construct(private readonly OrderToInvoiceMapper $invoiceMapper) {}

    public function build(Order $order, int $pennylaneCustomerId): CreateCommercialDocumentData
    {
        $config = config('pennylane');

        // Daté du jour de l'expédition. L'API exige une échéance de validité,
        // sans objet pour un bon de livraison : même date.
        $date = Carbon::now()->toDateString();

        return new CreateCommercialDocumentData(
            documentType: CommercialDocumentsResource::TYPE_SHIPPING_ORDER,
            pennylaneCustomerId: $pennylaneCustomerId,
            externalReference: self::externalReference($order),
            date: $date,
            deadline: $date,
            currency: strtoupper((string) $order->currency_code),
            lines: $this->lines($order),
            subject: $order->reference ? "Commande {$order->reference}" : null,
            description: $this->shippingAddress($order),
            language: (string) ($config['default_language'] ?? 'fr'),
        );
    }

    public static function externalReference(Order $order): string
    {
        return (string) config('pennylane.external_reference_prefix.delivery_note', 'delivery_').$order->id;
    }

    /**
     * Marchandises livrées, valorisées comme sur la facture (l'API exige un
     * prix par ligne). Le port n'est pas une marchandise : il reste sur la facture.
     *
     * @return array<int,InvoiceLineData>
     */
    private function lines(Order $order): array
    {
        return array_values(array_filter(
            $this->invoiceMapper->lines($order),
            fn (InvoiceLineData $line): bool => $line->unit !== 'service',
        ));
    }

    private function shippingAddress(Order $order): ?string
    {
        $address = $order->shippingAddress;
        if (! $address) {
            return null;
        }

        $lines = array_filter([
            trim(($address->company_name ?? '')),
            trim(($address->first_name ?? '').' '.($address->last_name ?? '')),
            $address->line_one ?? null,
            $address->line_two ?? null,
            $address->line_three ?? null,
            trim(($address->postcode ?? '').' '.($address->city ?? '')),
        ], fn ($v) => is_string($v) && trim($v) !== '');

        return $lines === [] ? null : "Livré à :\n".implode("\n", $lines);
    }
}
