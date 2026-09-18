<?php

declare(strict_types=1);

namespace Pko\Loyalty\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Lunar\DataTypes\Price;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Loyalty\Enums\GiftStatus;
use Pko\Loyalty\Mail\TierUnlockedAdminMail;
use Pko\Loyalty\Mail\TierUnlockedMail;
use Pko\Loyalty\Models\CustomerPoints;
use Pko\Loyalty\Models\GiftHistory;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\Loyalty\Models\PointsHistory;
use Pko\Loyalty\Models\Setting;
use Pko\MailTemplates\Support\AdminRecipient;

class LoyaltyManager
{
    public function pointsRatio(): float
    {
        $ratio = (float) Setting::get('points_ratio', (string) config('loyalty.default_ratio', 1));

        return $ratio > 0 ? $ratio : 1.0;
    }

    /**
     * Année civile du cycle de points en cours. Les points repartent à zéro
     * chaque 1er janvier (cf. `ensureCurrentYear()` et `loyalty:reset-annual`).
     */
    public function currentYear(): int
    {
        return (int) now()->year;
    }

    /**
     * Remet le solde à zéro s'il appartient à une année passée. Appelée avant
     * toute écriture sur le solde : la remise à zéro ne dépend donc pas du seul
     * passage du scheduler au 1er janvier. Ne sauvegarde pas.
     */
    public function ensureCurrentYear(CustomerPoints $cp): void
    {
        if ((int) $cp->points_year === $this->currentYear()) {
            return;
        }

        $cp->total_points = 0;
        $cp->current_tier_id = null;
        $cp->points_year = $this->currentYear();
    }

    /**
     * Remise à zéro annuelle de tous les soldes d'une année passée.
     *
     * @return int Nombre de soldes remis à zéro.
     */
    public function resetExpiredBalances(): int
    {
        return CustomerPoints::query()
            ->where(fn ($q) => $q->whereNull('points_year')->orWhere('points_year', '<', $this->currentYear()))
            ->update([
                'total_points' => 0,
                'current_tier_id' => null,
                'points_year' => $this->currentYear(),
            ]);
    }

    public function awardForOrder(Order $order): void
    {
        if ($order->customer_id === null) {
            return;
        }

        if (PointsHistory::query()->where('order_id', $order->id)->exists()) {
            return;
        }

        $totalHt = (int) ($order->sub_total instanceof Price
            ? $order->sub_total->value
            : ($order->getRawOriginal('sub_total') ?? 0));

        if ($totalHt <= 0) {
            return;
        }

        $ratio = $this->pointsRatio();
        // sub_total est en cents → on convertit en € HT avant division par ratio
        $points = (int) floor(($totalHt / 100) / $ratio);

        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $points, $totalHt): void {
            PointsHistory::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'order_reference' => $order->reference,
                'points_earned' => $points,
                'order_total_ht' => $totalHt,
            ]);

            $cp = CustomerPoints::firstOrNew(['customer_id' => $order->customer_id]);
            $this->ensureCurrentYear($cp);
            $oldTierId = $cp->current_tier_id;
            $cp->total_points = (int) $cp->total_points + $points;
            $cp->last_order_at = now();
            $cp->save();

            $this->unlockEligibleTiers($cp, $oldTierId, $order->id);
        });
    }

    /**
     * Retire les points d'une commande au prorata des remboursements réussis
     * (transactions Lunar `refund`, qui portent aussi les avoirs Pennylane).
     * Recalcule la cible depuis le cumul remboursé → idempotent, et un second
     * remboursement partiel ne retire que la différence.
     *
     * Une commande dont les points ont été gagnés une année passée est ignorée :
     * ces points ont déjà disparu avec la remise à zéro du 1er janvier.
     *
     * Si le solde repasse sous un palier débloqué cette année, le cadeau encore
     * en attente est annulé ; un cadeau déjà en préparation ou envoyé est gardé.
     *
     * @return int Points retirés par cet appel.
     */
    public function revokeForRefunds(Order $order): int
    {
        if ($order->customer_id === null) {
            return 0;
        }

        return DB::transaction(function () use ($order): int {
            $history = PointsHistory::query()->where('order_id', $order->id)->lockForUpdate()->first();

            if ($history === null || (int) $history->points_earned <= 0) {
                return 0;
            }

            if ((int) $history->created_at?->year !== $this->currentYear()) {
                return 0;
            }

            $refunded = (int) Transaction::query()
                ->where('order_id', $order->id)
                ->where('type', 'refund')
                ->where('success', true)
                ->sum('amount');

            if ($refunded <= 0) {
                return 0;
            }

            // Montants remboursés en TTC → comparés au total TTC de la commande.
            $orderTotal = (int) ($order->total instanceof Price
                ? $order->total->value
                : ($order->getRawOriginal('total') ?? 0));

            $earned = (int) $history->points_earned;
            $target = $orderTotal > 0
                ? min($earned, (int) round($earned * $refunded / $orderTotal))
                : $earned;

            $delta = $target - (int) $history->points_revoked;
            if ($delta <= 0) {
                return 0;
            }

            $history->update(['points_revoked' => $target]);

            $cp = CustomerPoints::query()->where('customer_id', $order->customer_id)->lockForUpdate()->first();
            if ($cp === null) {
                return $delta;
            }

            $this->ensureCurrentYear($cp);
            $cp->total_points = max(0, (int) $cp->total_points - $delta);
            $cp->current_tier_id = LoyaltyTier::query()
                ->where('active', true)
                ->where('points_required', '<=', $cp->total_points)
                ->orderByDesc('points_required')
                ->value('id');
            $cp->save();

            GiftHistory::query()
                ->where('customer_id', $cp->customer_id)
                ->where('year', $this->currentYear())
                ->where('status', GiftStatus::Pending)
                ->whereHas('tier', fn ($q) => $q->where('points_required', '>', $cp->total_points))
                ->delete();

            return $delta;
        });
    }

    /**
     * Débloque tous les paliers éligibles (points_required <= solde) qui n'ont pas
     * encore de GiftHistory pour ce client — pas seulement le plus haut atteint.
     * Sans ça, un palier ajouté après coup sous le solde déjà acquis d'un client
     * (ou un saut de plusieurs paliers en une seule commande) reste invisible :
     * ni "à venir" (déjà dépassé) ni "débloqué" (jamais inscrit en base).
     *
     * @return int Nombre de paliers nouvellement débloqués.
     */
    protected function unlockEligibleTiers(CustomerPoints $cp, ?int $oldTierId, ?int $orderId = null): int
    {
        $eligibleTiers = LoyaltyTier::query()
            ->where('active', true)
            ->where('points_required', '<=', $cp->total_points)
            ->orderBy('points_required')
            ->get();

        if ($eligibleTiers->isEmpty()) {
            return 0;
        }

        $highestTierId = $eligibleTiers->last()->id;
        if ($highestTierId !== $oldTierId) {
            $cp->current_tier_id = $highestTierId;
            $cp->save();
        }

        $alreadyUnlockedTierIds = GiftHistory::query()
            ->where('customer_id', $cp->customer_id)
            ->where('year', $this->currentYear())
            ->whereIn('tier_id', $eligibleTiers->pluck('id'))
            ->pluck('tier_id')
            ->all();

        $unlockedCount = 0;

        foreach ($eligibleTiers as $tier) {
            if (in_array($tier->id, $alreadyUnlockedTierIds, true)) {
                continue;
            }

            $history = GiftHistory::create([
                'customer_id' => $cp->customer_id,
                'tier_id' => $tier->id,
                'year' => $this->currentYear(),
                'order_id' => $orderId,
                'points_at_unlock' => $cp->total_points,
                'status' => GiftStatus::Pending,
                'unlocked_at' => now(),
            ]);

            $this->dispatchTierUnlocked($cp->customer_id, $tier, $cp->total_points, $history);
            $unlockedCount++;
        }

        return $unlockedCount;
    }

    /**
     * Rejoue le déblocage des paliers éligibles pour un client déjà existant,
     * hors flux commande (cf. `loyalty:recalculate`). Rattrape les paliers
     * ajoutés après coup ou sautés lors de commandes passées.
     */
    public function recalculateForCustomer(CustomerPoints $cp): int
    {
        if ((int) $cp->points_year !== $this->currentYear()) {
            return 0;
        }

        return $this->unlockEligibleTiers($cp, $cp->current_tier_id);
    }

    public function dispatchTierUnlocked(int $customerId, LoyaltyTier $tier, int $totalPoints, GiftHistory $history): void
    {
        $customer = Customer::find($customerId);

        if ($customer && $email = $this->resolveCustomerEmail($customer)) {
            $mail = new TierUnlockedMail($customer, $tier, $totalPoints);

            // `email_sent` ne doit refléter qu'un envoi réel : si le modèle est
            // désactivé en back-office, l'historique reste à false et le palier
            // pourra être notifié plus tard sans être considéré comme traité.
            if ($mail->shouldSend()) {
                Mail::to($email)->send($mail);
                $history->update(['email_sent' => true]);
            }
        }

        AdminRecipient::send(
            new TierUnlockedAdminMail($customer, $tier, $totalPoints),
            $history,
        );
    }

    protected function resolveCustomerEmail(Customer $customer): ?string
    {
        if ($user = $customer->users()->first()) {
            return $user->email ?? null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomerSnapshot(int $customerId): array
    {
        $cp = CustomerPoints::query()->where('customer_id', $customerId)->first();
        // Solde d'une année passée pas encore remis à zéro en base : il vaut 0.
        $totalPoints = $cp !== null && (int) $cp->points_year === $this->currentYear()
            ? (int) $cp->total_points
            : 0;

        $allTiers = LoyaltyTier::query()->where('active', true)->orderBy('points_required')->get();

        // Paliers déjà atteints (points_required <= solde) → point de départ du wizard.
        $prevPoints = (int) ($allTiers
            ->filter(fn ($t) => (int) $t->points_required <= $totalPoints)
            ->max('points_required') ?? 0);

        // Tous les cadeaux à venir (paliers strictement au-dessus du solde).
        $upcomingTiers = $allTiers
            ->filter(fn ($t) => (int) $t->points_required > $totalPoints)
            ->values();

        $nextTier = $upcomingTiers->first();

        $progress = 0.0;
        $pointsToNext = 0;
        if ($nextTier) {
            $progress = min(100.0, ($totalPoints / (int) $nextTier->points_required) * 100);
            $pointsToNext = (int) $nextTier->points_required - $totalPoints;
        }

        $giftHistory = GiftHistory::query()
            ->with('tier')
            ->where('customer_id', $customerId)
            ->orderByDesc('unlocked_at')
            ->get();

        // Les paliers sont re-débloquables chaque année : seuls ceux de l'année
        // en cours comptent comme « débloqués » dans la progression.
        $unlocked = $giftHistory->where('year', $this->currentYear())->values();

        $pointsHistory = PointsHistory::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->get();

        return [
            'total_points' => $totalPoints,
            'prev_points' => $prevPoints,
            'next_tier' => $nextTier,
            'upcoming_tiers' => $upcomingTiers,
            'progress_percent' => round($progress, 1),
            'points_to_next' => $pointsToNext,
            'unlocked_tiers' => $unlocked,
            'gift_history' => $giftHistory,
            'points_year' => $this->currentYear(),
            'points_history' => $pointsHistory,
            'total_active_tiers' => $allTiers->count(),
            'all_tiers_unlocked' => $allTiers->isNotEmpty() && $nextTier === null && $totalPoints > 0,
            'no_tiers_configured' => $allTiers->isEmpty(),
        ];
    }
}
