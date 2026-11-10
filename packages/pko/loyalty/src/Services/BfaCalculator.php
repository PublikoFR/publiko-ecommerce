<?php

declare(strict_types=1);

namespace Pko\Loyalty\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Loyalty\Enums\GiftType;
use Pko\Loyalty\Models\BfaPayout;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\PointsHistory;
use Pko\Loyalty\Support\BfaTranche;

/**
 * Calcul du BFA (bonus de fin d'année).
 *
 * Chaque déblocage d'un palier BFA ouvre une tranche, close au déblocage du
 * palier BFA suivant (même client, même année) ou au 31/12. Une tranche vaut
 * `bfa_fixed_amount + bfa_percent % × CA de la tranche` ; les tranches se
 * cumulent.
 *
 * CA = même base que les points : `points_history.order_total_ht` (HT hors
 * port), net des remboursements au prorata (Σ remboursé TTC / total TTC, comme
 * `LoyaltyManager::revokeForRefunds()`). La commande qui débloque un palier a
 * été passée AVANT le déblocage : elle compte dans la tranche précédente, pas
 * dans celle qu'elle ouvre.
 */
class BfaCalculator
{
    /**
     * @return Collection<int, BfaTranche>
     */
    public function tranches(int $customerId, int $year): Collection
    {
        $gifts = GiftHistory::query()
            ->with('tier')
            ->where('customer_id', $customerId)
            ->where('year', $year)
            ->whereHas('tier', fn ($q) => $q->where('gift_type', GiftType::Bfa->value))
            ->orderBy('unlocked_at')
            ->orderBy('id')
            ->get()
            ->values();

        if ($gifts->isEmpty()) {
            return collect();
        }

        $revenues = $this->revenuePerTranche($customerId, $year, $gifts);
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        return $gifts->map(function (GiftHistory $gift, int $i) use ($gifts, $revenues, $yearEnd, $customerId, $year): BfaTranche {
            $tier = $gift->tier;
            $next = $gifts->get($i + 1);
            $revenue = $revenues[$i] ?? 0;
            $fixed = (int) ($tier->bfa_fixed_amount ?? 0);
            $percent = number_format((float) ($tier->bfa_percent ?? 0), 2, '.', '');

            return new BfaTranche(
                giftHistoryId: (int) $gift->id,
                customerId: $customerId,
                tierId: $tier?->id,
                tierName: $tier?->name,
                year: $year,
                start: Carbon::parse($gift->unlocked_at),
                end: $next !== null ? Carbon::parse($next->unlocked_at) : $yearEnd->copy(),
                revenueHt: $revenue,
                fixedAmount: $fixed,
                percent: $percent,
                amount: $fixed + self::percentOf($revenue, $percent),
            );
        });
    }

    /** BFA acquis à ce jour sur l'année (toutes tranches), HT en cents. */
    public function acquiredToDate(int $customerId, ?int $year = null): int
    {
        return (int) $this->tranches($customerId, $year ?? (int) now()->year)->sum('amount');
    }

    /**
     * Pré-calcul de fin d'année : crée ou met à jour un versement par tranche.
     * Un versement émis (ou en cours d'émission) n'est jamais modifié ; le
     * montant ajusté par l'admin est conservé. Les brouillons dont la tranche
     * a disparu (déblocage annulé par un remboursement) sont supprimés.
     *
     * @return int Nombre de versements à jour pour l'année.
     */
    public function precompute(int $year): int
    {
        $customerIds = GiftHistory::query()
            ->where('year', $year)
            ->whereHas('tier', fn ($q) => $q->where('gift_type', GiftType::Bfa->value))
            ->distinct()
            ->pluck('customer_id');

        $kept = [];

        foreach ($customerIds as $customerId) {
            foreach ($this->tranches((int) $customerId, $year) as $tranche) {
                $kept[] = $tranche->giftHistoryId;

                DB::transaction(function () use ($tranche): void {
                    $payout = BfaPayout::query()
                        ->where('gift_history_id', $tranche->giftHistoryId)
                        ->lockForUpdate()
                        ->first();

                    // Émise, en cours, ou document distant déjà créé : montant gelé.
                    if ($payout !== null && ! $payout->isAmountEditable()) {
                        return;
                    }

                    $values = [
                        'customer_id' => $tranche->customerId,
                        'tier_id' => $tranche->tierId,
                        'tier_name' => $tranche->tierName,
                        'year' => $tranche->year,
                        'period_start' => $tranche->start,
                        'period_end' => $tranche->end,
                        'revenue_ht' => $tranche->revenueHt,
                        'fixed_amount' => $tranche->fixedAmount,
                        'percent' => $tranche->percent,
                        'computed_amount' => $tranche->amount,
                    ];

                    if ($payout === null) {
                        BfaPayout::create($values + [
                            'gift_history_id' => $tranche->giftHistoryId,
                            'external_reference' => BfaPayout::externalReferenceFor($tranche->year, $tranche->giftHistoryId),
                            'status' => BfaPayout::STATUS_DRAFT,
                        ]);

                        return;
                    }

                    $payout->update($values);
                });
            }
        }

        BfaPayout::query()
            ->where('year', $year)
            // Brouillons purs uniquement : une ligne en échec garde la trace
            // de l'erreur, et un document distant ne doit jamais perdre sa ligne.
            ->where('status', BfaPayout::STATUS_DRAFT)
            ->whereNull('pennylane_id')
            ->where(fn ($q) => $q->whereNull('gift_history_id')->orWhereNotIn('gift_history_id', $kept))
            ->delete();

        return count($kept);
    }

    /**
     * Affecte chaque commande de l'année à sa tranche et somme le CA net.
     *
     * @param  Collection<int, GiftHistory>  $gifts  triés par déblocage
     * @return array<int, int> index de tranche → CA HT net en cents
     */
    private function revenuePerTranche(int $customerId, int $year, Collection $gifts): array
    {
        $rows = PointsHistory::query()
            ->where('customer_id', $customerId)
            ->whereBetween('created_at', [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ])
            ->get(['order_id', 'order_total_ht', 'created_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $orderIds = $rows->pluck('order_id')->all();
        $orderTotals = Order::query()->whereIn('id', $orderIds)->get(['id', 'total'])
            ->mapWithKeys(fn (Order $o) => [$o->id => (int) ($o->getRawOriginal('total') ?? 0)]);
        $refunds = Transaction::query()
            ->whereIn('order_id', $orderIds)
            ->where('type', 'refund')
            ->where('success', true)
            ->groupBy('order_id')
            ->selectRaw('order_id, SUM(amount) as refunded')
            ->pluck('refunded', 'order_id');

        // Commande ayant débloqué un palier → index de la PREMIÈRE tranche ouverte par elle.
        $openedBy = [];
        foreach ($gifts as $i => $gift) {
            if ($gift->order_id !== null && ! isset($openedBy[(int) $gift->order_id])) {
                $openedBy[(int) $gift->order_id] = $i;
            }
        }

        $revenues = [];

        foreach ($rows as $row) {
            $orderId = (int) $row->order_id;

            if (isset($openedBy[$orderId])) {
                $index = $openedBy[$orderId] - 1;
            } else {
                $index = -1;
                foreach ($gifts as $i => $gift) {
                    if (Carbon::parse($gift->unlocked_at)->lte($row->created_at)) {
                        $index = $i;
                    }
                }
            }

            if ($index < 0) {
                continue; // avant le premier déblocage BFA
            }

            $revenues[$index] = ($revenues[$index] ?? 0) + self::netOfRefunds(
                (int) $row->order_total_ht,
                (int) ($refunds[$orderId] ?? 0),
                (int) ($orderTotals[$orderId] ?? 0),
            );
        }

        return $revenues;
    }

    /** HT net du prorata remboursé (remboursements en TTC, comparés au total TTC). */
    public static function netOfRefunds(int $totalHt, int $refundedTtc, int $totalTtc): int
    {
        if ($refundedTtc <= 0 || $totalHt <= 0) {
            return max(0, $totalHt);
        }

        if ($totalTtc <= 0 || $refundedTtc >= $totalTtc) {
            return 0;
        }

        return $totalHt - self::roundDiv($totalHt * $refundedTtc, $totalTtc);
    }

    /** `$percent` % de `$cents`, arrondi au centime (arithmétique entière). */
    public static function percentOf(int $cents, string $percent): int
    {
        $basisPoints = (int) round(((float) $percent) * 100);

        if ($cents <= 0 || $basisPoints <= 0) {
            return 0;
        }

        return self::roundDiv($cents * $basisPoints, 10_000);
    }

    private static function roundDiv(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
