<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Modifiers;

use Closure;
use Lunar\Base\ShippingModifier;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Pko\ShippingCommon\Support\WeightCalculator;

/**
 * Adds a free-shipping option when every cart line has pko_free_shipping = true.
 *
 * Carrier modifiers (AbstractCarrierModifier) already skip naturally when
 * fromCartTaxable() returns 0, so this modifier just surfaces the "free"
 * choice that would otherwise leave the checkout with no options at all.
 */
class FreeShippingModifier extends ShippingModifier
{
    public const IDENTIFIER = 'free_shipping';

    public function handle(Cart $cart, Closure $next)
    {
        if (WeightCalculator::allLinesFreeShipping($cart)) {
            $currency = $cart->currency ?? Currency::getDefault();
            $taxClass = TaxClass::getDefault();

            ShippingManifest::addOption(new ShippingOption(
                name: 'Livraison offerte',
                description: 'Tous les articles de votre commande sont expédiés directement par le fournisseur.',
                identifier: self::IDENTIFIER,
                price: new Price(0, $currency, 1),
                taxClass: $taxClass,
            ));
        }

        return $next($cart);
    }
}
