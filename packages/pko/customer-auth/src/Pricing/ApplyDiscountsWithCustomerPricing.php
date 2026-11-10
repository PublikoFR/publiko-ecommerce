<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Pricing;

use Closure;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;

/**
 * Remplace `Lunar\Pipelines\Cart\ApplyDiscounts` (classe `final`) dans
 * config/lunar/cart.php : applique les remises Lunar en arbitrant le non-cumul
 * avec la remise client % (cf. CartDiscountArbiter).
 */
class ApplyDiscountsWithCustomerPricing
{
    public function __construct(private readonly CartDiscountArbiter $arbiter) {}

    /** @param  Closure(CartContract): mixed  $next */
    public function handle(CartContract $cart, Closure $next): mixed
    {
        /** @var Cart $cart */
        $this->arbiter->apply($cart);

        return $next($cart);
    }
}
