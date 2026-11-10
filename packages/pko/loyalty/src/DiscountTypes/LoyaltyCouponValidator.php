<?php

declare(strict_types=1);

namespace Pko\Loyalty\DiscountTypes;

use Lunar\Base\Validation\CouponValidator;
use Lunar\Models\Discount;

/**
 * Le validateur Lunar ne reconnaît que `AmountOff` et `BuyXGetY` : sans cette
 * extension, le code d'un bon fidélité est refusé à la saisie dans le panier.
 *
 * Lié dans le conteneur à la place de `CouponValidator` (LoyaltyServiceProvider),
 * ce qui évite de toucher à `config/lunar/discounts.php`.
 */
class LoyaltyCouponValidator extends CouponValidator
{
    public function validate(string $coupon): bool
    {
        if (parent::validate($coupon)) {
            return true;
        }

        return Discount::query()
            ->where('type', LoyaltyVoucher::class)
            ->active()
            ->usable()
            ->where('coupon', '=', strtoupper($coupon))
            ->exists();
    }
}
