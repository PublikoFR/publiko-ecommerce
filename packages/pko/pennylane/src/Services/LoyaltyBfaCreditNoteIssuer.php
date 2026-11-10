<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Lunar\Models\Currency;
use Pko\Loyalty\Contracts\BfaCreditNoteIssuer;
use Pko\Loyalty\Models\BfaPayout;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;
use Pko\Pennylane\Dto\CreateCreditNoteData;
use Pko\Pennylane\Dto\InvoiceLineData;

/**
 * Avoir Pennylane autonome pour un versement de BFA fidélité.
 *
 * En v2, un avoir est une facture client à montants négatifs (même endpoint).
 * Celui-ci n'est rattaché à aucune facture : pas de `link_credit_note`
 * (contrairement à `CreditNoteSynchronizer`, qui crédite une facture précise).
 *
 * Idempotent via `external_reference` (`bfa_<année>_<déblocage>`) : en
 * reprise, l'avoir déjà créé est retrouvé et seulement finalisé si besoin.
 * Le document n'est PAS écrit dans `pko_pennylane_invoices` : cette table ne
 * porte que des pièces liées à une commande (e-mail « facture disponible »,
 * espace client). Le suivi vit sur `pko_loyalty_bfa_payouts`.
 */
final class LoyaltyBfaCreditNoteIssuer implements BfaCreditNoteIssuer
{
    public function __construct(
        private readonly PennylaneClient $client,
        private readonly CustomerMapper $customerMapper,
        private readonly CustomerInvoicesResource $invoices,
    ) {}

    public function issue(BfaPayout $payout): array
    {
        $customer = $payout->customer;
        if (! $customer) {
            throw new \RuntimeException("Versement BFA {$payout->id} sans client.");
        }

        $amount = $payout->amountToIssue();
        if ($amount <= 0) {
            throw new \RuntimeException("Versement BFA {$payout->id} : montant nul.");
        }

        $dto = $this->build($payout, $this->customerMapper->resolveForCustomer($customer), $amount);

        $remote = $this->invoices->findByExternalReference($payout->external_reference)
            ?? $this->invoices->create($dto->toArray());

        $pennylaneId = (int) $remote['id'];
        if (! CustomerInvoicesResource::isFinalized($remote)) {
            $remote = $this->invoices->finalize($pennylaneId);
        }

        return [
            'id' => $pennylaneId,
            'number' => isset($remote['invoice_number']) ? (string) $remote['invoice_number'] : null,
        ];
    }

    public function build(BfaPayout $payout, int $pennylaneCustomerId, int $amountCents): CreateCreditNoteData
    {
        return new CreateCreditNoteData(
            pennylaneCustomerId: $pennylaneCustomerId,
            customerInvoiceTemplateId: $this->client->resolveTemplateId(),
            parentInvoicePennylaneId: null,
            externalReference: $payout->external_reference,
            date: Carbon::now()->toDateString(),
            currency: strtoupper((string) (Currency::getDefault()?->code ?? 'EUR')),
            lines: [
                new InvoiceLineData(
                    label: $payout->label(),
                    quantity: 1.0,
                    unitAmount: -($amountCents / 100),
                    vatRate: (float) config('loyalty.bfa.vat_rate', 20),
                    unit: 'service',
                ),
            ],
            reason: 'Avoir de bonus de fin d\'année '.$payout->year,
            language: (string) config('pennylane.default_language', 'fr'),
        );
    }
}
