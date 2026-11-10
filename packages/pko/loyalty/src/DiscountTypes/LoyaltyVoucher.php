<?php

declare(strict_types=1);

namespace Pko\Loyalty\DiscountTypes;

use Lunar\Base\ValueObjects\Cart\DiscountBreakdown;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdownLine;
use Lunar\DataTypes\Price;
use Lunar\DiscountTypes\AbstractDiscountType;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\Contracts\Discount as DiscountContract;

/**
 * Bon d'achat fidélité : montant fixe HT, à usage unique, émis au déblocage
 * d'un palier (cf. `Pko\Loyalty\Services\LoyaltyVoucherService`).
 *
 * Type dédié plutôt que `AmountOff` pour deux raisons :
 * - `AmountOff` à montant fixe ne s'applique PAS du tout quand le panier est
 *   inférieur au montant du bon ; un bon fidélité s'applique plafonné au
 *   panier, le reste est perdu (usage unique) ;
 * - le type identifie le bon comme « fidélité » : la règle de non-cumul des
 *   remises le laisse toujours se cumuler (cf. `isLoyaltyVoucher()`).
 *
 * La remise s'AJOUTE à celles déjà posées sur les lignes (elle ne garde pas
 * « la meilleure » comme `AmountOff`) : appliquée en dernier (priorité 0), elle
 * porte sur le HT déjà remisé.
 */
class LoyaltyVoucher extends AbstractDiscountType
{
    /** Clé de `discounts.data` portant le montant HT en cents. */
    public const DATA_AMOUNT = 'voucher_amount';

    public static function isLoyaltyVoucher(?DiscountContract $discount): bool
    {
        return $discount !== null && $discount->type === static::class;
    }

    public function getName(): string
    {
        return "Bon d'achat fidélité";
    }

    public function apply(CartContract $cart): CartContract
    {
        /** @var Cart $cart */
        if (! $this->checkDiscountConditions($cart)) {
            return $cart;
        }

        $lines = $this->getEligibleLines($cart);
        $available = (int) $lines->sum(fn ($line) => ($line->subTotalDiscounted ?? $line->subTotal)->value);
        $value = min((int) ($this->discount->data[self::DATA_AMOUNT] ?? 0), $available);

        if ($value <= 0) {
            return $cart;
        }

        $remaining = $value;
        $affectedLines = collect();

        foreach ($lines as $line) {
            if ($remaining <= 0) {
                break;
            }

            $lineAvailable = ($line->subTotalDiscounted ?? $line->subTotal)->value;
            $share = (int) floor($lineAvailable * $value / $available);
            $amount = min($share, $lineAvailable, $remaining);

            if ($amount <= 0) {
                continue;
            }

            $this->addToLine($line, $amount, $cart);
            $remaining -= $amount;
            $affectedLines->push(new DiscountBreakdownLine(line: $line, quantity: $line->quantity));
        }

        // Reliquat d'arrondi : posé sur la première ligne qui a encore de la marge.
        foreach ($lines as $line) {
            if ($remaining <= 0) {
                break;
            }

            $amount = min($remaining, ($line->subTotalDiscounted ?? $line->subTotal)->value);
            if ($amount <= 0) {
                continue;
            }

            $this->addToLine($line, $amount, $cart);
            $remaining -= $amount;

            if (! $affectedLines->contains(fn (DiscountBreakdownLine $b) => $b->line === $line)) {
                $affectedLines->push(new DiscountBreakdownLine(line: $line, quantity: $line->quantity));
            }
        }

        if (! $cart->discounts) {
            $cart->discounts = collect();
        }
        $cart->discounts->push($this);

        $this->addDiscountBreakdown($cart, new DiscountBreakdown(
            price: new Price($value - $remaining, $cart->currency, 1),
            lines: $affectedLines,
            discount: $this->discount,
        ));

        return $cart;
    }

    private function addToLine(mixed $line, int $amount, CartContract $cart): void
    {
        /** @var Cart $cart */
        $discountTotal = (int) ($line->discountTotal?->value ?? 0) + $amount;

        $line->discountTotal = new Price($discountTotal, $cart->currency, 1);
        $line->subTotalDiscounted = new Price($line->subTotal->value - $discountTotal, $cart->currency, 1);
    }
}
