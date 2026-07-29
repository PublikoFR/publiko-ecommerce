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
use Pko\ShippingCommon\Pricing\CalculatedShippingOption;
use Pko\ShippingCommon\Pricing\ShippingCalculator;

/**
 * Unique ShippingModifier Lunar du projet.
 *
 * Délègue tout le calcul à ShippingCalculator::calculate() puis traduit
 * le ShippingQuote en entrées ShippingManifest Lunar. Remplace :
 *   - AbstractCarrierModifier / ChronopostModifier / ColissimoModifier
 *   - FreeShippingModifier
 *   - FrancoModifier
 *   - SurchargeModifier
 */
class UnifiedShippingModifier extends ShippingModifier
{
    /**
     * Identifier par défaut présélectionné au checkout (rétro-compat ShippingOptions::mount()).
     */
    public const CHRONO13_IDENTIFIER = ShippingCalculator::DEFAULT_OPTION_IDENTIFIER;

    public function handle(Cart $cart, Closure $next): mixed
    {
        $quote = app(ShippingCalculator::class)->calculate($cart);

        if ($quote->isEmpty()) {
            return $next($cart);
        }

        $currency = $cart->currency ?? Currency::getDefault();
        $taxClass = TaxClass::getDefault();

        foreach ($quote->options as $opt) {
            ShippingManifest::addOption($this->toShippingOption($opt, $currency, $taxClass));
        }

        return $next($cart);
    }

    private function toShippingOption(
        CalculatedShippingOption $opt,
        Currency $currency,
        ?TaxClass $taxClass,
    ): ShippingOption {
        $meta = [
            'carrier' => $opt->carrierCode,
            'service_code' => $opt->serviceCode,
            'franco' => $opt->franco,
            'quote' => $opt->isSentinel,
            'grid_price_cents' => $opt->gridPriceCents,
            'flat_price_cents' => $opt->flatPriceCents,
            'surcharge_cents' => $opt->autoSurchargeCents,
            'requires_pickup_point' => $opt->requiresPickupPoint,
        ];

        return new ShippingOption(
            name: $opt->name,
            description: $opt->description,
            identifier: $opt->identifier,
            price: new Price($opt->totalPriceCents(), $currency, 1),
            taxClass: $taxClass,
            meta: $meta,
        );
    }
}
