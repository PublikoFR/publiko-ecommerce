<?php

declare(strict_types=1);

namespace Pko\Loyalty\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lunar\Models\Channel;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Pko\Loyalty\DiscountTypes\LoyaltyVoucher;
use Pko\Loyalty\Models\GiftHistory;

/**
 * Émission et désactivation des bons d'achat fidélité (remises Lunar de type
 * `LoyaltyVoucher`).
 *
 * Un bon = une remise Lunar : code unique, montant fixe HT, usage unique
 * (`max_uses = 1`), réservée au client (`customer_discount`), valable
 * `VALIDITY_DAYS` jours. Un déblocage (GiftHistory) porte au plus un bon :
 * `gift_history.discount_id` est unique et le handle dérive de l'id du déblocage.
 */
class LoyaltyVoucherService
{
    public const VALIDITY_DAYS = 60;

    public const HANDLE_PREFIX = 'loyalty-voucher-';

    public const CODE_PREFIX = 'FID-';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DISABLED = 'disabled';

    /**
     * Émet le bon d'un déblocage de palier « bon d'achat ». Idempotent : renvoie
     * le bon existant s'il a déjà été émis. Null si le palier n'est pas un bon
     * d'achat ou n'a pas de montant.
     */
    public function issueFor(GiftHistory $history): ?Discount
    {
        return DB::transaction(function () use ($history): ?Discount {
            $locked = GiftHistory::query()->with('tier')->lockForUpdate()->find($history->id);

            if ($locked === null) {
                return null;
            }

            if ($locked->discount_id !== null) {
                return $locked->discount;
            }

            $tier = $locked->tier;
            $amount = (int) ($tier?->voucher_amount ?? 0);

            if ($tier === null || ! $tier->isVoucher()) {
                return null;
            }

            if ($amount <= 0) {
                Log::warning('Loyalty voucher not issued: tier has no amount', ['tier_id' => $tier->id, 'gift_history_id' => $locked->id]);

                return null;
            }

            $discount = Discount::create([
                'name' => "Bon d'achat fidélité — palier {$tier->name} — client #{$locked->customer_id}",
                'handle' => self::HANDLE_PREFIX.$locked->id,
                'coupon' => $this->uniqueCode(),
                'type' => LoyaltyVoucher::class,
                'starts_at' => now(),
                'ends_at' => now()->addDays(self::VALIDITY_DAYS),
                'max_uses' => 1,
                'max_uses_per_user' => null,
                // Appliqué après toutes les autres remises (ordre `priority desc`).
                'priority' => 0,
                'stop' => false,
                'data' => [
                    LoyaltyVoucher::DATA_AMOUNT => $amount,
                    'gift_history_id' => $locked->id,
                ],
            ]);

            // Les hooks Lunar n'activent que le canal et le groupe par défaut : le
            // bon doit valoir quel que soit le groupe du client (B2B).
            $discount->channels()->sync(
                Channel::query()->pluck('id')->mapWithKeys(fn ($id) => [$id => [
                    'enabled' => true,
                    'starts_at' => now(),
                    'ends_at' => null,
                ]])->all()
            );
            $discount->customerGroups()->sync(
                CustomerGroup::query()->pluck('id')->mapWithKeys(fn ($id) => [$id => [
                    'enabled' => true,
                    'visible' => false,
                    'starts_at' => now(),
                    'ends_at' => null,
                ]])->all()
            );
            $discount->customers()->sync([$locked->customer_id]);

            $locked->update(['discount_id' => $discount->id]);
            $history->setAttribute('discount_id', $discount->id);
            $history->setRelation('discount', $discount);

            return $discount;
        });
    }

    /**
     * Désactive le bon d'un déblocage s'il n'a pas été utilisé.
     *
     * @return bool true si un bon a été désactivé.
     */
    public function disableIfUnused(GiftHistory $history): bool
    {
        $discount = $history->discount;

        if ($discount === null || (int) $discount->uses > 0) {
            return false;
        }

        $data = (array) $discount->data;
        $data['disabled_at'] = now()->toIso8601String();

        $discount->update([
            'ends_at' => now(),
            'data' => $data,
        ]);

        return true;
    }

    public function isUsed(?Discount $discount): bool
    {
        return $discount !== null && (int) $discount->uses > 0;
    }

    public static function status(Discount $discount): string
    {
        if ((int) $discount->uses > 0) {
            return self::STATUS_USED;
        }

        if (! empty(((array) $discount->data)['disabled_at'])) {
            return self::STATUS_DISABLED;
        }

        if ($discount->ends_at !== null && $discount->ends_at->isPast()) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_USED => 'Utilisé',
            self::STATUS_EXPIRED => 'Expiré',
            self::STATUS_DISABLED => 'Annulé',
            default => 'Valable',
        };
    }

    public static function amount(Discount $discount): int
    {
        return (int) (((array) $discount->data)[LoyaltyVoucher::DATA_AMOUNT] ?? 0);
    }

    private function uniqueCode(): string
    {
        do {
            // Lettres/chiffres ambigus retirés : un code se recopie à la main.
            $code = self::CODE_PREFIX.strtr(
                strtoupper(Str::random(8)),
                ['0' => '7', 'O' => 'P', '1' => '8', 'I' => 'J', 'L' => 'M'],
            );
        } while (Discount::query()->where('coupon', $code)->exists());

        return $code;
    }
}
