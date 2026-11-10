<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Lunar\Models\Currency;
use Pko\Loyalty\Contracts\BfaCreditNoteIssuer;
use Pko\Loyalty\Models\BfaPayout;
use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
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
 * Idempotent : l'id Pennylane est mémorisé sur la ligne dès la création
 * (avant la finalisation) ; en reprise, le document est relu par cet id, ou
 * retrouvé par `external_reference` (`bfa_<année>_<déblocage>`), puis
 * seulement finalisé — et jamais si son montant HT diffère du montant local.
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

        $remote = $this->findRemote($payout);
        $existing = $remote !== null;

        $remote ??= $this->invoices->create(
            $this->build($payout, $this->customerMapper->resolveForCustomer($customer), $amount)->toArray(),
        );

        // Mémorisé AVANT la finalisation : si elle échoue, la ligne sait qu'un
        // document distant existe (montant gelé, reprise par id).
        $this->rememberRemote($payout, (int) $remote['id']);

        if ($existing) {
            $this->assertSameAmount($payout, $remote, $amount);
        }

        $pennylaneId = (int) $remote['id'];
        if (! CustomerInvoicesResource::isFinalized($remote)) {
            $remote = $this->invoices->finalize($pennylaneId);
        }

        return [
            'id' => $pennylaneId,
            'number' => isset($remote['invoice_number']) ? (string) $remote['invoice_number'] : null,
        ];
    }

    /**
     * Document déjà créé pour ce versement : par son id mémorisé, sinon par
     * sa référence externe (plantage entre la création et l'écriture locale).
     *
     * @return array<string,mixed>|null
     */
    private function findRemote(BfaPayout $payout): ?array
    {
        if ($payout->pennylane_id !== null) {
            try {
                return $this->invoices->get((int) $payout->pennylane_id);
            } catch (PennylaneApiException $e) {
                if (! $e->isNotFound()) {
                    throw $e;
                }
                // Brouillon supprimé dans Pennylane : on repart de zéro.
                $payout->forceFill(['pennylane_id' => null])->save();
            }
        }

        return $this->invoices->findByExternalReference($payout->external_reference);
    }

    private function rememberRemote(BfaPayout $payout, int $pennylaneId): void
    {
        if ((int) $payout->pennylane_id !== $pennylaneId) {
            $payout->forceFill(['pennylane_id' => $pennylaneId])->save();
        }
    }

    /**
     * Un document distant n'est jamais finalisé avec un montant différent du
     * montant local : on lève une erreur explicite (ligne en échec) plutôt que
     * de comptabiliser silencieusement un autre montant.
     *
     * @param  array<string,mixed>  $remote
     */
    private function assertSameAmount(BfaPayout $payout, array $remote, int $expectedCents): void
    {
        $raw = $remote['currency_amount_before_tax'] ?? null;
        if (! is_scalar($raw) || ! preg_match('/^-?\d+(\.\d+)?$/', (string) $raw)) {
            throw new \RuntimeException(sprintf(
                'Avoir Pennylane %d (%s) déjà existant : montant HT illisible, émission interrompue. Vérifiez le document dans Pennylane.',
                (int) $remote['id'],
                $payout->external_reference,
            ));
        }

        $remoteCents = self::toCents((string) $raw);
        if ($remoteCents === $expectedCents) {
            return;
        }

        $finalized = CustomerInvoicesResource::isFinalized($remote);
        throw new \RuntimeException(sprintf(
            'Avoir déjà %s chez Pennylane (n° %d) avec %s € HT, montant local %s € HT. %s',
            $finalized ? 'finalisé' : 'créé en brouillon',
            (int) $remote['id'],
            self::formatCents($remoteCents),
            self::formatCents($expectedCents),
            $finalized
                ? 'Un avoir finalisé ne se modifie plus : régularisez dans Pennylane.'
                : 'Supprimez le brouillon dans Pennylane ou rétablissez le montant local, puis relancez.',
        ));
    }

    /** « -70.5 » → 7050 (valeur absolue, sans float). */
    private static function toCents(string $decimal): int
    {
        [$int, $dec] = explode('.', ltrim($decimal, '-')) + [1 => ''];

        return (int) $int * 100 + (int) str_pad(substr($dec, 0, 2), 2, '0');
    }

    private static function formatCents(int $cents): string
    {
        return intdiv($cents, 100).','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
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
