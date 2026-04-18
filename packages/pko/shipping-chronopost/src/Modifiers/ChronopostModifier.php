<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Modifiers;

use Closure;
use Lunar\Base\ShippingModifier;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Support\WeightCalculator;
use Pko\ShippingCommon\Support\ZoneResolver;

class ChronopostModifier extends ShippingModifier
{
    public function handle(Cart $cart, Closure $next)
    {
        $address = $cart->shippingAddress;

        if ($address === null) {
            return $next($cart);
        }

        $country = $address->country?->iso2 ?? 'FR';
        $postcode = (string) ($address->postcode ?? '');

        if (! ZoneResolver::isMetropole($postcode, $country)) {
            return $next($cart);
        }

        $weightKg = WeightCalculator::fromCart($cart);
        if ($weightKg <= 0) {
            return $next($cart);
        }

        /** @var CarrierClient $client */
        $client = app('pko.shipping.carrier.chronopost');

        $quotes = $client->quote(new QuoteRequest(
            weightKg: $weightKg,
            destinationPostcode: $postcode,
            destinationCountry: $country,
        ));

        if ($quotes === []) {
            return $next($cart);
        }

        $currency = $cart->currency ?? Currency::getDefault();
        $taxClass = TaxClass::getDefault();

        foreach ($quotes as $quote) {
            ShippingManifest::addOption(new ShippingOption(
                name: 'Chronopost — '.$quote->serviceLabel,
                description: 'Livraison '.$quote->serviceLabel.' (France métropolitaine)',
                identifier: 'chronopost.'.$quote->serviceCode,
                price: new Price($quote->priceCents, $currency, 1),
                taxClass: $taxClass,
                meta: [
                    'carrier' => 'chronopost',
                    'service_code' => $quote->serviceCode,
                    'weight_kg' => $weightKg,
                ],
            ));
        }

        return $next($cart);
    }
}
