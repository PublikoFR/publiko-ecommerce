<?php

declare(strict_types=1);

namespace Mde\Loyalty\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lunar\DataTypes\Price;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Mde\Loyalty\Enums\GiftStatus;
use Mde\Loyalty\Models\CustomerPoints;
use Mde\Loyalty\Models\GiftHistory;
use Mde\Loyalty\Models\LoyaltyTier;
use Mde\Loyalty\Models\PointsHistory;
use Mde\Loyalty\Models\Setting;
use Mde\Loyalty\Notifications\TierUnlockedAdmin;
use Mde\Loyalty\Notifications\TierUnlockedCustomer;

class LoyaltyManager
{
    public function pointsRatio(): float
    {
        $ratio = (float) Setting::get('points_ratio', (string) config('mde-loyalty.default_ratio', 1));

        return $ratio > 0 ? $ratio : 1.0;
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
            $oldTierId = $cp->current_tier_id;
            $cp->total_points = (int) $cp->total_points + $points;
            $cp->last_order_at = now();
            $cp->save();

            $this->unlockEligibleTiers($cp, $oldTierId, $order->id);
        });
    }

    protected function unlockEligibleTiers(CustomerPoints $cp, ?int $oldTierId, int $orderId): void
    {
        $tier = LoyaltyTier::query()
            ->where('active', true)
            ->where('points_required', '<=', $cp->total_points)
            ->orderByDesc('points_required')
            ->first();

        if (! $tier) {
            return;
        }

        if ($tier->id === $oldTierId) {
            return;
        }

        $cp->current_tier_id = $tier->id;
        $cp->save();

        $exists = GiftHistory::query()
            ->where('customer_id', $cp->customer_id)
            ->where('tier_id', $tier->id)
            ->exists();

        if ($exists) {
            return;
        }

        $history = GiftHistory::create([
            'customer_id' => $cp->customer_id,
            'tier_id' => $tier->id,
            'order_id' => $orderId,
            'points_at_unlock' => $cp->total_points,
            'status' => GiftStatus::Pending,
            'unlocked_at' => now(),
        ]);

        $this->dispatchTierUnlocked($cp->customer_id, $tier, $cp->total_points, $history);
    }

    public function dispatchTierUnlocked(int $customerId, LoyaltyTier $tier, int $totalPoints, GiftHistory $history): void
    {
        $customer = Customer::find($customerId);

        if ($customer && $email = $this->resolveCustomerEmail($customer)) {
            Notification::route('mail', $email)
                ->notify(new TierUnlockedCustomer($customer, $tier, $totalPoints));
            $history->update(['email_sent' => true]);
        }

        $adminEmail = (string) (Setting::get('admin_email') ?: config('mde-loyalty.admin_email'));
        if ($adminEmail !== '') {
            Notification::route('mail', $adminEmail)
                ->notify(new TierUnlockedAdmin($customer, $tier, $totalPoints));
        }
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
        $totalPoints = (int) ($cp->total_points ?? 0);

        $allTiers = LoyaltyTier::query()->where('active', true)->orderBy('points_required')->get();

        $nextTier = $allTiers->firstWhere(fn ($t) => (int) $t->points_required > $totalPoints);

        $progress = 0.0;
        $pointsToNext = 0;
        if ($nextTier) {
            $progress = min(100.0, ($totalPoints / (int) $nextTier->points_required) * 100);
            $pointsToNext = (int) $nextTier->points_required - $totalPoints;
        }

        $unlocked = GiftHistory::query()
            ->with('tier')
            ->where('customer_id', $customerId)
            ->orderBy('unlocked_at')
            ->get();

        return [
            'total_points' => $totalPoints,
            'next_tier' => $nextTier,
            'progress_percent' => round($progress, 1),
            'points_to_next' => $pointsToNext,
            'unlocked_tiers' => $unlocked,
            'total_active_tiers' => $allTiers->count(),
            'all_tiers_unlocked' => $allTiers->isNotEmpty() && $nextTier === null && $totalPoints > 0,
            'no_tiers_configured' => $allTiers->isEmpty(),
        ];
    }
}
