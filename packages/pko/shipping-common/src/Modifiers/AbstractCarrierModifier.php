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
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Models\ShippingSurcharge;
use Pko\ShippingCommon\Support\WeightCalculator;
use Pko\ShippingCommon\Support\ZoneResolver;

/**
 * Template shipping modifier for PKO carriers.
 *
 * Subclass declares only carrierCode(). Parent handles:
 *   - address / country / postcode extraction
 *   - zone filtering (default: France métropolitaine)
 *   - weight computation
 *   - quote call
 *   - injection of ShippingOption entries into the manifest
 *
 * Override shouldQuote() to implement a different zone policy.
 */
abstract class AbstractCarrierModifier extends ShippingModifier
{
    abstract protected function carrierCode(): string;

    public function handle(Cart $cart, Closure $next)
    {
        $address = $cart->shippingAddress;
        if ($address === null) {
            return $next($cart);
        }

        $country = $address->country?->iso2 ?? 'FR';
        $postcode = (string) ($address->postcode ?? '');

        if (! $this->shouldQuote($country, $postcode)) {
            return $next($cart);
        }

        $weightKg = WeightCalculator::fromCartTaxable($cart);
        if ($weightKg <= 0) {
            return $next($cart);
        }

        $quotes = $this->resolveClient()->quote(new QuoteRequest(
            weightKg: $weightKg,
            destinationPostcode: $postcode,
            destinationCountry: $country,
        ));

        if ($quotes === []) {
            return $next($cart);
        }

        $currency = $cart->currency ?? Currency::getDefault();
        $taxClass = TaxClass::getDefault();
        $displayName = $this->carrierDisplayName();

        foreach ($quotes as $quote) {
            ShippingManifest::addOption(new ShippingOption(
                name: $displayName.' — '.$quote->serviceLabel,
                description: 'Livraison '.$quote->serviceLabel.' (France métropolitaine)',
                identifier: $this->carrierCode().'.'.$quote->serviceCode,
                price: new Price($quote->priceCents, $currency, 1),
                taxClass: $taxClass,
                meta: [
                    'carrier' => $this->carrierCode(),
                    'service_code' => $quote->serviceCode,
                    'weight_kg' => $weightKg,
                ],
            ));
        }

        return $next($cart);
    }

    protected function shouldQuote(string $country, string $postcode): bool
    {
        if (ZoneResolver::isMetropole($postcode, $country)) {
            return true;
        }

        // Ouvre la Corse si un supplément auto 'corse' est actif — tous les carriers concernés.
        if (ZoneResolver::isCorse($postcode, $country)) {
            return $this->hasActiveCorseSurcharge();
        }

        return false;
    }

    private function hasActiveCorseSurcharge(): bool
    {
        return ShippingSurcharge::query()
            ->where('enabled', true)
            ->where('mode', 'auto')
            ->where(function ($q): void {
                $q->where('code', 'corse')
                    ->orWhereJsonContains('rule->type', 'corse')
                    ->orWhereJsonContains('rule->postcode_prefix', '20');
            })
            ->exists();
    }

    protected function resolveClient(): CarrierClient
    {
        return app('pko.shipping.carrier.'.$this->carrierCode());
    }

    protected function carrierDisplayName(): string
    {
        $def = app(CarrierRegistry::class)->get($this->carrierCode());

        return $def?->displayName ?? ucfirst($this->carrierCode());
    }
}
